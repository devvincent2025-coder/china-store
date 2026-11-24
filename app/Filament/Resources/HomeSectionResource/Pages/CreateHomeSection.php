<?php

namespace App\Filament\Resources\HomeSectionResource\Pages;

use App\Filament\Resources\HomeSectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeSection extends CreateRecord
{
    protected static string $resource = HomeSectionResource::class;

    // Straight to the edit page, where product sections get their "Products in this section" table.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
