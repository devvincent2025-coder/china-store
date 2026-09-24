<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Resources\VendorResource;
use App\Filament\Support\CsvExporter;
use App\Models\Vendor;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendors extends ListRecords
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportCsv()),
            Actions\CreateAction::make(),
        ];
    }

    private function exportCsv()
    {
        $rows = Vendor::query()->latest()->get()->map(fn (Vendor $vendor) => [
            $vendor->business_name,
            $vendor->contact_email,
            $vendor->status,
            $vendor->documents_status,
            $vendor->bank_verification_status,
            $vendor->commission_rate,
            $vendor->created_at->toDateTimeString(),
        ]);

        return CsvExporter::stream(
            'vendors-'.now()->format('Y-m-d').'.csv',
            ['Business Name', 'Email', 'Status', 'Documents', 'Bank Verification', 'Commission %', 'Joined'],
            $rows,
        );
    }
}
