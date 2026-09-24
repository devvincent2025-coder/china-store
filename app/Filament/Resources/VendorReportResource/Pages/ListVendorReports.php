<?php

namespace App\Filament\Resources\VendorReportResource\Pages;

use App\Filament\Resources\VendorReportResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorReports extends ListRecords
{
    protected static string $resource = VendorReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
