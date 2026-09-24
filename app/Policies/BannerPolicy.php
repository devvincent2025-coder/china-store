<?php

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

class BannerPolicy
{
    public function update(User $user, Banner $banner): bool
    {
        return $user->vendor?->id === $banner->vendor_id;
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $user->vendor?->id === $banner->vendor_id;
    }
}
