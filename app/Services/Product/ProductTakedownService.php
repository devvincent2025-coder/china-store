<?php

namespace App\Services\Product;

use App\Models\AdminActivityLog;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ProductAutoFlagged;
use App\Notifications\ProductRestored;
use App\Notifications\ProductTakenDown;
use Illuminate\Support\Facades\Notification;

/**
 * Shared by Filament\Resources\ProductResource (direct takedown) and
 * Filament\Resources\ProductReportResource (takedown from a buyer report) so both paths log the
 * same activity trail and notify the vendor identically.
 */
class ProductTakedownService
{
    public function takeDown(Product $product, string $reason, User $admin): void
    {
        $product->update([
            'status' => 'archived',
            'takedown_reason' => $reason,
            'taken_down_at' => now(),
            'taken_down_by' => $admin->id,
        ]);

        $product->vendor->user->notify(new ProductTakenDown($product, $reason));

        AdminActivityLog::record('product.taken_down', $product, "Took down \"{$product->getTranslation('title', 'en')}\": {$reason}");
    }

    /**
     * Called from ProductReportController::store() once a product accrues enough open reports
     * (the threshold is the 'product_report_flag_threshold' setting — see Filament\Pages\Settings)
     * — pulls the product down immediately (buyer safety first) and separately records flagged_at/
     * flagged_reason so admins can tell "the system did this pending investigation" apart from a
     * moderator's own direct takedown, even though both end up with status='archived'. Does NOT
     * touch the vendor's account — suspending them is always a deliberate admin decision after
     * investigating, never automatic (see VendorSuspensionService).
     */
    public function autoFlagForReports(Product $product, int $reportCount): void
    {
        $reason = "Automatically taken down after reaching {$reportCount} open reports — pending admin investigation.";

        $product->update([
            'status' => 'archived',
            'takedown_reason' => $reason,
            'taken_down_at' => now(),
            'taken_down_by' => null,
            'flagged_at' => now(),
            'flagged_reason' => $reason,
        ]);

        $product->vendor->user->notify(new ProductTakenDown($product, $reason));
        Notification::send(User::role(['admin', 'super_admin'])->get(), new ProductAutoFlagged($product, $reportCount));

        AdminActivityLog::record('product.auto_flagged', $product, $reason);
    }

    /**
     * Restores to 'draft' rather than straight back to 'published' — the vendor should
     * consciously re-publish it themselves rather than have it silently go live again without
     * their awareness of what, if anything, they need to fix first.
     */
    public function restore(Product $product): void
    {
        $product->update([
            'status' => 'draft',
            'takedown_reason' => null,
            'taken_down_at' => null,
            'taken_down_by' => null,
            'flagged_at' => null,
            'flagged_reason' => null,
        ]);

        $product->vendor->user->notify(new ProductRestored($product));

        AdminActivityLog::record('product.restored', $product, "Restored \"{$product->getTranslation('title', 'en')}\"");
    }
}
