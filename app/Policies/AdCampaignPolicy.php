<?php

namespace App\Policies;

use App\Models\AdCampaign;
use App\Models\User;

class AdCampaignPolicy
{
    public function update(User $user, AdCampaign $adCampaign): bool
    {
        return $user->vendor?->id === $adCampaign->vendor_id;
    }
}
