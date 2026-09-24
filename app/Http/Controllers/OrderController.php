<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Models\VendorOrder;
use App\Models\VendorOrderDispute;
use App\Notifications\VendorOrderDisputeOpened;
use App\Services\Escrow\EscrowService;
use App\Services\Storage\SupabaseStorageException;
use App\Services\Storage\SupabaseStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly EscrowService $escrow,
        private readonly SupabaseStorageService $storage,
    ) {
    }

    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->with('vendorOrders.vendor.store', 'vendorOrders.items')
            ->latest()
            ->paginate(10);

        return Inertia::render('Orders/Index', ['orders' => $orders]);
    }

    public function show(Request $request, string $order): Response
    {
        $order = Order::where('order_number', $order)->firstOrFail();
        $this->authorize('view', $order);

        $order->load([
            'vendorOrders.vendor.store',
            'vendorOrders.items.product',
            'vendorOrders.items.review',
            'vendorOrders.statusHistory',
            'vendorOrders.disputes',
        ]);

        $order->vendorOrders->each(function (VendorOrder $vendorOrder) {
            $vendorOrder->disputes->each(fn (VendorOrderDispute $dispute) => $this->attachEvidenceUrls($dispute));
        });

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'escrowReturnWindowDays' => (int) Setting::get('escrow_return_window_days', config('services.escrow.return_window_days')),
        ]);
    }

    /**
     * Buyer-facing "Download Invoice" — a single self-contained PDF covering every vendor's items
     * on the order (a buyer's cart can span multiple vendors, but they only ever see one invoice
     * per order, same as the checkout/order-confirmation experience). Uses the buyer's OWN locale
     * for product titles so the PDF they download reads in whichever language they're browsing in.
     */
    public function downloadInvoice(Request $request, string $order): SymfonyResponse
    {
        $order = Order::where('order_number', $order)->firstOrFail();
        $this->authorize('view', $order);

        $order->load([
            'user',
            'vendorOrders.vendor.store',
            'vendorOrders.coupon',
            'vendorOrders.items',
        ]);

        return Pdf::loadView('exports.pdf.order-invoice', ['order' => $order])
            ->setPaper('a4')
            ->download("Invoice-{$order->order_number}.pdf");
    }

    /**
     * The buyer's "Confirm Receipt" action — releases escrow to the vendor immediately rather than
     * waiting out the full return window. Only available once delivered, with no open dispute.
     */
    public function confirmReceipt(Request $request, VendorOrder $vendorOrder): RedirectResponse
    {
        $this->authorizeBuyerOwnsOrder($request, $vendorOrder);

        try {
            $this->escrow->release($vendorOrder);
        } catch (ValidationException $e) {
            // EscrowService::release() throws for things entirely out of the buyer's control (the
            // vendor's bank details rejected by Korapay, KYC incomplete, etc.) — there's no form
            // field on this page for that error to attach to (it's a single button, not a form), so
            // it's surfaced as a toast via the flash bridge instead of silently doing nothing, which
            // is what happened before this: the request failed server-side but the buyer just saw
            // the page reload with no visible change at all.
            return back()->with('error', $e->validator->errors()->first('escrow'));
        }

        return back()->with('success', 'Thanks for confirming — the vendor has been paid out.');
    }

    /**
     * The buyer's "Report a Problem" action — opens a dispute instead of confirming receipt, which
     * pauses escrow release (see VendorOrder::isEligibleForEscrowRelease()) until an admin resolves
     * it via Filament\Resources\VendorOrderDisputeResource.
     */
    public function storeDispute(Request $request, VendorOrder $vendorOrder): RedirectResponse
    {
        $this->authorizeBuyerOwnsOrder($request, $vendorOrder);

        if ($vendorOrder->fulfillment_status !== 'delivered') {
            throw ValidationException::withMessages(['dispute' => 'You can only report a problem once the order has been delivered.']);
        }

        if ($vendorOrder->hasOpenDispute()) {
            throw ValidationException::withMessages(['dispute' => 'You already have an open report for this order.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'in:damaged,wrong_item,not_as_described,missing_item,other'],
            'description' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['image', 'max:10240'],
        ]);

        $evidence = collect($request->file('evidence', []))
            ->map(fn ($file) => $this->storage->uploadPrivate($file->getRealPath(), 'dispute-evidence'))
            ->values()
            ->all();

        $dispute = $vendorOrder->disputes()->create([
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'evidence' => $evidence,
            // The vendor's window to respond with their own side before an admin can step in and
            // resolve it regardless — see VendorOrderDispute::RESPONSE_WINDOW_HOURS.
            'vendor_response_deadline' => now()->addHours(VendorOrderDispute::RESPONSE_WINDOW_HOURS),
        ]);

        $vendorOrder->vendor->user->notify(new VendorOrderDisputeOpened($dispute));

        return back()->with('success', 'Thanks — our team will review this and get back to you.');
    }

    /**
     * A signing failure (Supabase hiccup, or an object that's gone missing) must never take down
     * the whole order page just to show a thumbnail — it's degraded, not broken, so this logs and
     * quietly returns no URL for that one photo rather than letting the exception propagate.
     */
    private function signEvidence(array $paths): array
    {
        return collect($paths)
            ->map(function (string $path) {
                try {
                    return $this->storage->signedUrl($path);
                } catch (SupabaseStorageException $e) {
                    Log::warning('Failed to sign dispute evidence URL', ['path' => $path, 'message' => $e->getMessage()]);

                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    private function attachEvidenceUrls(VendorOrderDispute $dispute): void
    {
        $dispute->setAttribute('evidence_urls', $this->signEvidence($dispute->evidence ?? []));
        $dispute->setAttribute('vendor_evidence_urls', $this->signEvidence($dispute->vendor_evidence ?? []));
    }

    private function authorizeBuyerOwnsOrder(Request $request, VendorOrder $vendorOrder): void
    {
        abort_unless($vendorOrder->order->user_id === $request->user()->id, 403);
    }
}
