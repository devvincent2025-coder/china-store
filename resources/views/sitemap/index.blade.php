<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap><loc>{{ $staticUrl }}</loc></sitemap>
    <sitemap><loc>{{ $categoriesUrl }}</loc></sitemap>
    <sitemap><loc>{{ $storesUrl }}</loc></sitemap>
    @for ($i = 1; $i <= $productPages; $i++)
    <sitemap><loc>{{ route('sitemap.products', ['page' => $i]) }}</loc></sitemap>
    @endfor
</sitemapindex>
