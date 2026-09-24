<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoicesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly Collection $invoices,
        private readonly bool $includeVendorColumn,
    ) {
    }

    public function collection(): Collection
    {
        return $this->invoices;
    }

    public function title(): string
    {
        return 'Invoices';
    }

    public function headings(): array
    {
        $headings = ['Invoice #', 'Date'];

        if ($this->includeVendorColumn) {
            $headings[] = 'Vendor';
        }

        return [
            ...$headings,
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Subtotal (₦)',
            'Discount (₦)',
            'VAT (₦)',
            'Shipping (₦)',
            'Total (₦)',
            'Amount Paid (₦)',
            'Balance Due (₦)',
            'Status',
            'Allows Partial Payment',
            'Due Date',
            'Sent At',
            'Paid At',
        ];
    }

    public function map($invoice): array
    {
        /** @var Invoice $invoice */
        $row = [
            $invoice->invoice_number,
            $invoice->created_at->format('Y-m-d H:i'),
        ];

        if ($this->includeVendorColumn) {
            $row[] = $invoice->vendor?->business_name;
        }

        return [
            ...$row,
            $invoice->customer_name,
            $invoice->customer_email,
            $invoice->customer_phone,
            $invoice->subtotal / 100,
            $invoice->discount / 100,
            $invoice->vat_amount / 100,
            $invoice->shipping_amount / 100,
            $invoice->total / 100,
            $invoice->amount_paid / 100,
            $invoice->balanceDue() / 100,
            str_replace('_', ' ', $invoice->status),
            $invoice->allow_partial_payment ? 'Yes' : 'No',
            $invoice->due_date?->format('Y-m-d') ?? '—',
            $invoice->sent_at?->format('Y-m-d H:i') ?? '—',
            $invoice->paid_at?->format('Y-m-d H:i') ?? '—',
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
