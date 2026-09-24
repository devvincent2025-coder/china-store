<?php

namespace App\Filament\Resources\VendorOrderDisputeResource\Pages;

use App\Filament\Resources\VendorOrderDisputeResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorOrderDisputes extends ListRecords
{
    protected static string $resource = VendorOrderDisputeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
