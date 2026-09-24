<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Spatie roles are scoped to a guard — every user-facing role in this app uses the default
        // 'web' guard, so it's set here rather than exposed as a form field vendors could get wrong.
        $data['guard_name'] = 'web';

        return $data;
    }
}
