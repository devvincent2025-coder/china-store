<?php

namespace App\Exports;

use App\Models\VendorOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The full-detail counterpart to exports/pdf/orders.blade.php — every column, since a spreadsheet
 * (unlike a printed page) has no width constraint. $includeVendorColumn is true only for the
 * platform-wide admin export (App\Services\Export\OrderExportQuery::build() with $vendor = null).
 */
class OrdersExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly Collection $orders,
        private readonly bool $includeVendorColumn,
    ) {
    }

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function title(): string
    {
        return 'Orders';
    }

    public function headings(): array
    {
        $headings = ['Order #', 'Date', 'Buyer Name', 'Buyer Email'];

        if ($this->includeVendorColumn) {
            $headings[] = 'Vendor';
        }

        return [
            ...$headings,
            'Items',
            'Coupon',
            'Original Subtotal (₦)',
            'Discount (₦)',
            'VAT (₦)',
            'Shipping (₦)',
            'Total (₦)',
            'Commission Rate (%)',
            'Commission (₦)',
            'Payout (₦)',
            'Escrow Status',
            'Fulfillment Status',
            'Payment Status',
            'Tracking Carrier',
            'Tracking Number',
            'Delivered At',
        ];
    }

    public function map($vendorOrder): array
    {
        /** @var VendorOrder $vendorOrder */
        $row = [
            $vendorOrder->order?->order_number,
            $vendorOrder->created_at->format('Y-m-d H:i'),
            $vendorOrder->order?->user?->name,
            $vendorOrder->order?->user?->email,
        ];

        if ($this->includeVendorColumn) {
            $row[] = $vendorOrder->vendor?->business_name;
        }

        return [
            ...$row,
            $vendorOrder->items->sum('quantity'),
            $vendorOrder->coupon?->code ?? '—',
            $vendorOrder->original_subtotal / 100,
            $vendorOrder->discount / 100,
            $vendorOrder->vat_amount / 100,
            $vendorOrder->shipping_amount / 100,
            $vendorOrder->total / 100,
            $vendorOrder->commission_rate,
            $vendorOrder->commission_amount / 100,
            $vendorOrder->payout_amount / 100,
            str_replace('_', ' ', $vendorOrder->escrow_status),
            $vendorOrder->fulfillment_status,
            $vendorOrder->order?->payment_status,
            $vendorOrder->tracking_carrier ?? '—',
            $vendorOrder->tracking_number ?? '—',
            $vendorOrder->delivered_at?->format('Y-m-d H:i') ?? '—',
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
