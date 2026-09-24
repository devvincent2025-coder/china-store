@extends('exports.pdf.layout')

@section('content')
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Total Invoices</div>
            <div class="summary-value">{{ number_format($invoices->count()) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Invoiced</div>
            <div class="summary-value">{{ \App\Support\Money::naira($invoices->sum('total')) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Collected</div>
            <div class="summary-value">{{ \App\Support\Money::naira($invoices->sum('amount_paid')) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Outstanding</div>
            <div class="summary-value">{{ \App\Support\Money::naira($invoices->sum(fn ($i) => $i->balanceDue())) }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                @if (! $vendor)
                    <th>Vendor</th>
                @endif
                <th>Customer</th>
                <th class="text-right">Total</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->created_at->format('d M Y') }}</td>
                    @if (! $vendor)
                        <td>{{ $invoice->vendor?->business_name }}</td>
                    @endif
                    <td>{{ $invoice->customer_name }}</td>
                    <td class="text-right">{{ \App\Support\Money::naira($invoice->total) }}</td>
                    <td class="text-right">{{ \App\Support\Money::naira($invoice->amount_paid) }}</td>
                    <td class="text-right">{{ \App\Support\Money::naira($invoice->balanceDue()) }}</td>
                    <td>
                        <span class="badge {{ match($invoice->status) {
                            'paid' => 'badge-green',
                            'partially_paid' => 'badge-amber',
                            'sent' => 'badge-blue',
                            'void' => 'badge-red',
                            default => 'badge-gray',
                        } }}">{{ str_replace('_', ' ', $invoice->status) }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $vendor ? 6 : 7 }}" class="text-muted">No invoices found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
