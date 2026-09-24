<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly Collection $products,
        private readonly bool $includeVendorColumn,
    ) {
    }

    public function collection(): Collection
    {
        return $this->products;
    }

    public function title(): string
    {
        return 'Products';
    }

    public function headings(): array
    {
        $headings = ['SKU', 'Title (EN)', 'Title (ZH)', 'Title (FR)'];

        if ($this->includeVendorColumn) {
            $headings[] = 'Vendor';
        }

        return [
            ...$headings,
            'Category',
            'Price (₦)',
            'Compare Price (₦)',
            'Discount (%)',
            'Stock',
            'Status',
            'Featured',
            'Views',
            'Avg Rating',
            'Reviews',
            'Created At',
        ];
    }

    public function map($product): array
    {
        /** @var Product $product */
        $row = [
            $product->sku,
            $product->getTranslation('title', 'en'),
            $product->getTranslation('title', 'zh'),
            $product->getTranslation('title', 'fr'),
        ];

        if ($this->includeVendorColumn) {
            $row[] = $product->vendor?->business_name;
        }

        return [
            ...$row,
            $product->category?->getTranslation('name', 'en'),
            $product->price / 100,
            $product->compare_price ? $product->compare_price / 100 : null,
            $product->discount_percentage ?? 0,
            $product->stock,
            $product->status,
            $product->is_featured ? 'Yes' : 'No',
            $product->views_count,
            $product->reviews_avg_rating ? round($product->reviews_avg_rating, 1) : null,
            $product->reviews_count,
            $product->created_at->format('Y-m-d H:i'),
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
