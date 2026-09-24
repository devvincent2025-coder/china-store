<?php

namespace App\Services\Vendor;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\VendorReactivated;
use App\Notifications\VendorSuspended;

/**
 * A deliberate, manual admin action — never automatic. The system will auto-flag and take down a
 * single product on repeat reports (see App\Services\Product\ProductTakedownService), but deciding
 * to suspend the whole vendor account always happens here, after a human investigates. Suspending
 * blocks the vendor's own dashboard (App\Http\Middleware\EnsureVendorIsActive checks
 * status === 'active') and their products stop being publicly listed
 * (Product::scopePubliclyListed() only shows a vendor's products via their own relation, but the
 * storefront routes gate on vendor status too — see VendorStoreController).
 */
class VendorSuspensionService
{
    public function suspend(Vendor $vendor, User $admin, string $reason): void
    {
        $vendor->update([
            'status' => 'suspended',
            'suspension_reason' => $reason,
            'suspended_at' => now(),
            'suspended_by' => $admin->id,
        ]);

        $vendor->user->notify(new VendorSuspended($reason));

        AdminActivityLog::record('vendor.suspended', $vendor, "Suspended {$vendor->business_name}: {$reason}");
    }

    /**
     * Reactivates straight back to 'active' rather than 'pending' — a previously-approved vendor
     * shouldn't have to redo onboarding just because they were suspended and then cleared.
     */
    public function reactivate(Vendor $vendor): void
    {
        $vendor->update([
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_at' => null,
            'suspended_by' => null,
        ]);

        $vendor->user->notify(new VendorReactivated);

        AdminActivityLog::record('vendor.reactivated', $vendor, "Reactivated {$vendor->business_name}");
    }
}
