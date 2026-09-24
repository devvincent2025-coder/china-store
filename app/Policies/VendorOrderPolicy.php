<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorOrder;

class VendorOrderPolicy
{
    public function view(User $user, VendorOrder $vendorOrder): bool
    {
        return $user->vendor?->id === $vendorOrder->vendor_id;
    }

    public function update(User $user, VendorOrder $vendorOrder): bool
    {
        return $user->vendor?->id === $vendorOrder->vendor_id;
    }
}
