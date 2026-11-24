<?php

namespace App\Filament\Resources\HomeSectionResource\Pages;

use App\Filament\Resources\HomeSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomeSections extends ListRecords
{
    protected static string $resource = HomeSectionResource::class;

    protected ?string $subheading = 'Everything on the storefront homepage, top to bottom. Drag rows to reorder; open a product section to pick its products and deal prices.';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add section'),
        ];
    }
}
