<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorOrder;
use App\Models\VendorOrderDispute;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderShipped;
use App\Models\User;
use App\Notifications\VendorRespondedToDispute;
use App\Services\Storage\SupabaseStorageException;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly SupabaseStorageService $storage)
    {
    }

    public function index(Request $request): Response
    {
        $vendorOrders = $request->user()->vendor->vendorOrders()
            ->with('order.user', 'items', 'coupon')
            ->when($request->string('q')->toString(), function ($query, $term) {
                $query->whereHas('order', function ($query) use ($term) {
                    $query->where('order_number', 'like', "%{$term}%")
                        ->orWhereHas('user', function ($query) use ($term) {
                            $query->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                });
            })
            ->when($request->string('fulfillment_status')->toString(), fn ($query, $status) => $query->where('fulfillment_status', $status))
            ->when($request->date('date_from'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date('date_to'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($request->boolean('has_coupon'), fn ($query) => $query->whereNotNull('coupon_id'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Vendor/Orders/Index', [
            'vendorOrders' => $vendorOrders,
            'vendor' => $request->user()->vendor,
            'filters' => (object) $request->only(['q', 'fulfillment_status', 'date_from', 'date_to', 'has_coupon']),
        ]);
    }

    public function show(Request $request, VendorOrder $vendorOrder): Response
    {
        $this->authorize('view', $vendorOrder);

        $vendorOrder->load('order.user', 'items.product', 'items.variant', 'coupon', 'statusHistory', 'disputes');

        $vendorOrder->disputes->each(fn (VendorOrderDispute $dispute) => $this->attachEvidenceUrls($dispute));

        return Inertia::render('Vendor/Orders/Show', [
            'vendor' => $request->user()->vendor,
            'vendorOrder' => $vendorOrder,
        ]);
    }

    /**
     * The vendor's chance to give their side of a dispute (with evidence) before an admin steps in
     * regardless — see VendorOrderDispute::RESPONSE_WINDOW_HOURS and Filament\Resources\
     * VendorOrderDisputeResource, which surfaces this response to whoever resolves it. This is
     * additive, not a race against the deadline: an admin CAN still resolve an overdue dispute, but
     * a late response is still shown to them if it arrives before that happens.
     */
    public function respondToDispute(Request $request, VendorOrder $vendorOrder, VendorOrderDispute $dispute): RedirectResponse
    {
        $this->authorize('update', $vendorOrder);

        abort_unless($dispute->vendor_order_id === $vendorOrder->id, 404);

        if (! $dispute->isOpen()) {
            throw ValidationException::withMessages(['response' => 'This dispute has already been resolved.']);
        }

        if ($dispute->hasVendorResponded()) {
            throw ValidationException::withMessages(['response' => 'You have already responded to this dispute.']);
        }

        $validated = $request->validate([
            'vendor_response' => ['required', 'string', 'max:2000'],
            'vendor_evidence' => ['nullable', 'array', 'max:5'],
            'vendor_evidence.*' => ['image', 'max:10240'],
        ]);

        $evidence = collect($request->file('vendor_evidence', []))
            ->map(fn ($file) => $this->storage->uploadPrivate($file->getRealPath(), 'dispute-evidence'))
            ->values()
            ->all();

        $dispute->update([
            'vendor_response' => $validated['vendor_response'],
            'vendor_evidence' => $evidence,
            'vendor_responded_at' => now(),
        ]);

        Notification::send(User::role(['admin', 'super_admin'])->get(), new VendorRespondedToDispute($dispute));

        return back()->with('success', 'Your response has been sent to our team for review.');
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

    public function update(Request $request, VendorOrder $vendorOrder): RedirectResponse
    {
        $this->authorize('update', $vendorOrder);

        $validated = $request->validate([
            'fulfillment_status' => ['required', 'in:processing,shipped,delivered,cancelled'],
            'tracking_number' => [
                'nullable', 'string', 'max:100',
                // A tracking number identifies one physical shipment — reusing it across two
                // orders would let one order's tracking page/webhook confusion leak into another's.
                Rule::unique('vendor_orders', 'tracking_number')->ignore($vendorOrder->id),
            ],
            'tracking_carrier' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'tracking_number.unique' => 'This tracking number is already in use on another order.',
        ]);

        $previousStatus = $vendorOrder->fulfillment_status;
        $wasShipped = $previousStatus === 'shipped';
        $wasDelivered = $previousStatus === 'delivered';
        $wasCancelled = $previousStatus === 'cancelled';

        $vendorOrder->update([
            'fulfillment_status' => $validated['fulfillment_status'],
            'tracking_number' => $validated['tracking_number'] ?? null,
            'tracking_carrier' => $validated['tracking_carrier'] ?? null,
            'shipped_at' => $validated['fulfillment_status'] === 'shipped' && ! $wasShipped ? now() : $vendorOrder->shipped_at,
            'delivered_at' => $validated['fulfillment_status'] === 'delivered' && ! $wasDelivered ? now() : $vendorOrder->delivered_at,
            // Starts the return window the buyer's "Confirm Receipt"/"Report a Problem" choice and
            // the daily escrow:release-eligible command both key off — see EscrowService.
            'return_window_expires_at' => $validated['fulfillment_status'] === 'delivered' && ! $wasDelivered
                ? now()->addDays((int) \App\Models\Setting::get('escrow_return_window_days', config('services.escrow.return_window_days')))
                : $vendorOrder->return_window_expires_at,
        ]);

        // Only log a new history entry when the status actually changed — editing just the
        // tracking number on an unchanged status shouldn't add a duplicate stepper step.
        if ($validated['fulfillment_status'] !== $previousStatus) {
            $vendorOrder->statusHistory()->create([
                'status' => $validated['fulfillment_status'],
                'note' => $validated['note'] ?? null,
            ]);
        }

        if ($validated['fulfillment_status'] === 'shipped' && ! $wasShipped) {
            $vendorOrder->order->user->notify(new OrderShipped($vendorOrder));
        }

        if ($validated['fulfillment_status'] === 'delivered' && ! $wasDelivered) {
            $vendorOrder->order->user->notify(new OrderDelivered($vendorOrder));
        }

        if ($validated['fulfillment_status'] === 'cancelled' && ! $wasCancelled) {
            $vendorOrder->order->user->notify(new OrderCancelled($vendorOrder));
        }

        return back()->with('success', 'Order updated.');
    }
}
