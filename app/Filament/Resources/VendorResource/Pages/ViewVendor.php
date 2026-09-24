<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Resources\VendorResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    /**
     * A page's getHeaderActions() requires Filament\Actions\Action instances specifically —
     * Filament\Tables\Actions\Action (what the table's ->actions([...]) needs) is a sibling class,
     * not a subclass, of it, so passing one here throws "Header actions must be an instance of
     * Filament\Actions\Action...". Each *Action() helper on VendorResource takes an optional
     * $actionClass override for exactly this reason — the table calls it with no argument (default
     * Tables\Actions\Action), this page passes Action::class.
     */
    protected function getHeaderActions(): array
    {
        return [
            VendorResource::approveDocumentsAction(Action::class),
            VendorResource::rejectDocumentsAction(Action::class),
            VendorResource::approveBankChangeAction(Action::class),
            VendorResource::rejectBankChangeAction(Action::class),
            VendorResource::approveIdentityAction(Action::class),
            VendorResource::rejectIdentityAction(Action::class),
            VendorResource::suspendAction(Action::class),
            VendorResource::reactivateAction(Action::class),
        ];
    }
}
