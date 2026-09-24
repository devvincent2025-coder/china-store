<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Notifications\InvoiceIssued;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = $request->user()->vendor->invoices()
            ->when($request->string('q')->toString(), function ($query, $term) {
                $query->where(function ($query) use ($term) {
                    $query->where('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_email', 'like', "%{$term}%")
                        ->orWhere('invoice_number', 'like', "%{$term}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->date('date_from'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date('date_to'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Vendor/Invoices/Index', [
            'vendor' => $request->user()->vendor,
            'invoices' => $invoices,
            'filters' => (object) $request->only(['q', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Vendor/Invoices/Create', [
            'vendor' => $request->user()->vendor,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $invoice = DB::transaction(function () use ($request, $validated) {
            $subtotal = collect($validated['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
            $this->assertDiscountValid($subtotal, $validated['discount']);
            $total = ($subtotal - $validated['discount']) + $validated['vat_amount'] + $validated['shipping_amount'];

            $invoice = $request->user()->vendor->invoices()->create([
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $validated['discount'],
                'vat_amount' => $validated['vat_amount'],
                'shipping_amount' => $validated['shipping_amount'],
                'total' => $total,
                'status' => 'draft',
                'allow_partial_payment' => $validated['allow_partial_payment'],
                'minimum_partial_amount' => $validated['allow_partial_payment'] ? ($validated['minimum_partial_amount'] ?? null) : null,
                'due_date' => $validated['due_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            return $invoice;
        });

        return redirect()->route('vendor.invoices.show', $invoice)->with('success', 'Invoice created.');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load('items', 'payments');

        // due_date is cast to 'date', which Eloquent still serializes as a full ISO datetime by
        // default — override it to a plain Y-m-d string so it round-trips cleanly through an
        // <input type="date"> on the frontend.
        $invoiceData = $invoice->toArray();
        $invoiceData['due_date'] = $invoice->due_date?->toDateString();

        return Inertia::render('Vendor/Invoices/Show', [
            'vendor' => $request->user()->vendor,
            'invoice' => $invoiceData,
            'payUrl' => route('invoices.pay.show', $invoice->public_token),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        if (! $invoice->isEditable()) {
            abort(403, 'Only draft invoices can be edited.');
        }

        $invoice->load('items');

        $invoiceData = $invoice->toArray();
        $invoiceData['due_date'] = $invoice->due_date?->toDateString();

        return Inertia::render('Vendor/Invoices/Edit', [
            'vendor' => $request->user()->vendor,
            'invoice' => $invoiceData,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be edited.']);
        }

        $validated = $this->validated($request);

        DB::transaction(function () use ($invoice, $validated) {
            $subtotal = collect($validated['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
            $this->assertDiscountValid($subtotal, $validated['discount']);
            $total = ($subtotal - $validated['discount']) + $validated['vat_amount'] + $validated['shipping_amount'];

            $invoice->update([
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $validated['discount'],
                'vat_amount' => $validated['vat_amount'],
                'shipping_amount' => $validated['shipping_amount'],
                'total' => $total,
                'allow_partial_payment' => $validated['allow_partial_payment'],
                'minimum_partial_amount' => $validated['allow_partial_payment'] ? ($validated['minimum_partial_amount'] ?? null) : null,
                'due_date' => $validated['due_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $invoice->items()->delete();

            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);
            }
        });

        return redirect()->route('vendor.invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be deleted.']);
        }

        $invoice->delete();

        return redirect()->route('vendor.invoices.index')->with('success', 'Invoice deleted.');
    }

    /**
     * Emails the customer named on the invoice with a link to the public payment page. Draft
     * invoices move to "sent"; an already-sent/partially-paid invoice can be resent (e.g. as a
     * reminder) without changing its status.
     */
    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->status === 'void') {
            throw ValidationException::withMessages(['invoice' => 'This invoice has been voided.']);
        }

        if ($invoice->items()->doesntExist()) {
            throw ValidationException::withMessages(['invoice' => 'Add at least one line item before sending.']);
        }

        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        $invoice->update(['sent_at' => now()]);

        Notification::route('mail', $invoice->customer_email)
            ->notify(new InvoiceIssued($invoice));

        return back()->with('success', 'Invoice sent to '.$invoice->customer_email.'.');
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->isFullyPaid()) {
            throw ValidationException::withMessages(['invoice' => 'A fully paid invoice cannot be voided.']);
        }

        $invoice->update(['status' => 'void']);

        return back()->with('success', 'Invoice voided.');
    }

    /**
     * total/subtotal are unsigned columns — a discount larger than the goods subtotal would make
     * total negative and fail at the database, so reject it up front with a clear message instead.
     */
    private function assertDiscountValid(int $subtotalKobo, int $discountKobo): void
    {
        if ($discountKobo > $subtotalKobo) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot be greater than the items subtotal.']);
        }
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'allow_partial_payment' => ['boolean'],
            'minimum_partial_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Naira in, kobo stored — every money value in the request arrives as a naira decimal from
        // the form, same convention as Vendor/ProductController::validated().
        foreach ($validated['items'] as $i => $item) {
            $validated['items'][$i]['unit_price'] = (int) round($item['unit_price'] * 100);
        }

        $validated['discount'] = (int) round(($validated['discount'] ?? 0) * 100);
        $validated['vat_amount'] = (int) round(($validated['vat_amount'] ?? 0) * 100);
        $validated['shipping_amount'] = (int) round(($validated['shipping_amount'] ?? 0) * 100);
        $validated['allow_partial_payment'] = $request->boolean('allow_partial_payment');
        $validated['minimum_partial_amount'] = isset($validated['minimum_partial_amount'])
            ? (int) round($validated['minimum_partial_amount'] * 100)
            : null;

        return $validated;
    }
}
