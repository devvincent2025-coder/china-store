<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One row per product, columns matching Google Merchant Center's spreadsheet feed spec exactly
 * (https://support.google.com/merchants/answer/7052112) — this file is meant to be uploaded to a
 * Google Sheet and connected as a "Scheduled fetch" (Sheets) feed in Merchant Center, NOT the same
 * thing as the always-live XML feed at /feeds/google-merchant.xml (App\Http\Controllers\
 * GoogleMerchantFeedController) — Merchant Center supports both; this one exists because a Sheets
 * feed is what most small sellers actually set up first, and because the user asked for something
 * they can open/inspect/edit in Excel before syncing.
 *
 * Only Product::scopePubliclyListed() rows are included — a product hidden from the storefront
 * (unverified vendor, vacation mode, etc.) has no business being submitted to Google Shopping
 * either.
 *
 * Columns intentionally left OUT (configure these directly in Merchant Center account settings
 * instead, since they're account-wide, not per-product): shipping cost/time, tax settings.
 */
class GoogleMerchantExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function collection(): Collection
    {
        return Product::query()
            ->publiclyListed()
            ->with(['images', 'vendor.store', 'category'])
            ->orderBy('id')
            ->get();
    }

    public function title(): string
    {
        return 'Google Merchant Feed';
    }

    public function headings(): array
    {
        return [
            'id',
            'title',
            'description',
            'link',
            'image_link',
            'additional_image_link',
            'availability',
            'price',
            'sale_price',
            'condition',
            'brand',
            'mpn',
            'identifier_exists',
            'google_product_category',
            'product_type',
        ];
    }

    public function map($product): array
    {
        /** @var Product $product */
        $images = $product->images;
        $primaryImage = $images->first();
        $extraImages = $images->skip(1)->take(10)->map(fn ($image) => $image->display_url)->filter()->implode(',');

        $storeName = $product->vendor?->store?->name ?: $product->vendor?->business_name;
        $priceNaira = number_format($product->price / 100, 2, '.', '');
        $hasSale = $product->compare_price && $product->compare_price > $product->price;

        return [
            $product->id,
            Str::limit($product->title, 150, ''),
            Str::limit(strip_tags($product->description ?? ''), 5000, ''),
            route('products.show', $product),
            $primaryImage?->display_url,
            $extraImages ?: null,
            $product->isInStock() ? 'in_stock' : 'out_of_stock',
            // "in_stock" price is always the non-sale price when a sale_price is also given —
            // Merchant Center's own convention, not this app's.
            $hasSale ? number_format($product->compare_price / 100, 2, '.', '') : $priceNaira.' NGN',
            $hasSale ? $priceNaira.' NGN' : null,
            'new',
            $storeName,
            $product->sku,
            // No GTIN/UPC/EAN tracked for these products — telling Google that plainly avoids the
            // feed being rejected for "missing identifier" instead of just skipping the field.
            'false',
            $product->category?->name,
            $product->category?->name,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF5000']],
        ]);

        return [];
    }
}
