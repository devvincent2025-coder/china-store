<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
    <title>{{ config('app.name') }} Product Feed</title>
    <link>{{ config('app.url') }}</link>
    <description>Products available for purchase on {{ config('app.name') }}</description>
    @foreach($products as $product)
    <item>
        <g:id>{{ $product->id }}</g:id>
        <title>{{ \Illuminate\Support\Str::limit($product->title, 150, '') }}</title>
        <description>{{ \Illuminate\Support\Str::limit(strip_tags($product->description ?? ''), 5000, '') }}</description>
        <link>{{ route('products.show', $product) }}</link>
        @php($image = $product->images->first())
        @if($image)
        <g:image_link>{{ $image->display_url }}</g:image_link>
        @endif
        @if($product->images->skip(1)->isNotEmpty())
        @foreach($product->images->skip(1)->take(10) as $extra)
        <g:additional_image_link>{{ $extra->display_url }}</g:additional_image_link>
        @endforeach
        @endif
        <g:availability>{{ $product->isInStock() ? 'in_stock' : 'out_of_stock' }}</g:availability>
        <g:price>{{ number_format($product->price / 100, 2, '.', '') }} NGN</g:price>
        @if($product->compare_price && $product->compare_price > $product->price)
        <g:sale_price>{{ number_format($product->price / 100, 2, '.', '') }} NGN</g:sale_price>
        @endif
        <g:condition>new</g:condition>
        <g:brand>{{ $product->vendor?->store?->name ?? $product->vendor?->business_name ?? config('app.name') }}</g:brand>
        @if($product->sku)
        <g:mpn>{{ $product->sku }}</g:mpn>
        @endif
        @if($product->category)
        <g:product_type>{{ $product->category->name }}</g:product_type>
        @endif
        <g:identifier_exists>false</g:identifier_exists>
    </item>
    @endforeach
</channel>
</rss>
