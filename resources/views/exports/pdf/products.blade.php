@extends('exports.pdf.layout')

@section('content')
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Total Products</div>
            <div class="summary-value">{{ number_format($products->count()) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Published</div>
            <div class="summary-value">{{ number_format($products->where('status', 'published')->count()) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Low Stock (&le;5)</div>
            <div class="summary-value">{{ number_format($products->where('stock', '<=', 5)->count()) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Stock Units</div>
            <div class="summary-value">{{ number_format($products->sum('stock')) }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Title</th>
                @if (! $vendor)
                    <th>Vendor</th>
                @endif
                <th>Category</th>
                <th class="text-right">Price</th>
                <th class="text-right">Stock</th>
                <th class="text-right">Rating</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>{{ $product->sku }}</td>
                    <td>{{ $product->getTranslation('title', 'en') }}</td>
                    @if (! $vendor)
                        <td>{{ $product->vendor?->business_name }}</td>
                    @endif
                    <td>{{ $product->category?->getTranslation('name', 'en') }}</td>
                    <td class="text-right">{{ \App\Support\Money::naira($product->price) }}</td>
                    <td class="text-right">{{ $product->stock }}</td>
                    <td class="text-right">{{ $product->reviews_avg_rating ? number_format($product->reviews_avg_rating, 1) : '—' }}</td>
                    <td>
                        <span class="badge {{ match($product->status) {
                            'published' => 'badge-green',
                            'draft' => 'badge-gray',
                            'archived' => 'badge-red',
                            default => 'badge-gray',
                        } }}">{{ $product->status }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $vendor ? 6 : 7 }}" class="text-muted">No products found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
