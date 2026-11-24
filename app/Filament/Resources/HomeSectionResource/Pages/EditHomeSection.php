<?php

namespace App\Filament\Resources\HomeSectionResource\Pages;

use App\Filament\Resources\HomeSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomeSection extends EditRecord
{
    protected static string $resource = HomeSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('View homepage')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url('/', shouldOpenInNewTab: true),
            Actions\DeleteAction::make(),
        ];
    }
}
