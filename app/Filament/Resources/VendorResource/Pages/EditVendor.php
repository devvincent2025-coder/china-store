<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Resources\VendorResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditVendor extends EditRecord
{
    protected static string $resource = VendorResource::class;

    /**
     * A page's getHeaderActions() requires Filament\Actions\Action instances specifically —
     * Filament\Tables\Actions\Action (what each *Action() helper builds by default, for the
     * table's own ->actions([...])) is a sibling class, not a subclass, of it, so passing one here
     * throws "Header actions must be an instance of Filament\Actions\Action...". See
     * ViewVendor::getHeaderActions()'s matching doc comment — this page was missing the
     * Action::class override those calls need, which 500'd this page every time it was opened.
     */
    protected function getHeaderActions(): array
    {
        return [
            VendorResource::approveDocumentsAction(Action::class),
            VendorResource::rejectDocumentsAction(Action::class),
            Actions\DeleteAction::make(),
        ];
    }
}
