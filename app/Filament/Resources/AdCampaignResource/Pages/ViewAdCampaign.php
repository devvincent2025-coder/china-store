<?php

namespace App\Filament\Resources\AdCampaignResource\Pages;

use App\Filament\Resources\AdCampaignResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewAdCampaign extends ViewRecord
{
    protected static string $resource = AdCampaignResource::class;

    /**
     * A page's getHeaderActions() requires Filament\Actions\Action instances specifically —
     * Filament\Tables\Actions\Action (what the table's ->actions([...]) needs) is a sibling class,
     * not a subclass, so passing one here throws "Header actions must be an instance of
     * Filament\Actions\Action...". See AdCampaignResource::approveAction()/rejectAction()'s
     * $actionClass parameter.
     */
    protected function getHeaderActions(): array
    {
        return [
            AdCampaignResource::approveAction(Action::class),
            AdCampaignResource::rejectAction(Action::class),
        ];
    }
}
