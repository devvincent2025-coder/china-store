<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VendorStore;
use App\Notifications\NewVendorReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Reports the VENDOR/store as a whole (scam, poor service, IP violation, etc.) — see
 * App\Http\Controllers\ProductReportController for the equivalent that flags one specific
 * listing instead. Bound on VendorStore (matching the buyer-facing 'vendors.show' route) since
 * that's what a buyer is actually looking at, but the report itself belongs to the Vendor.
 */
class VendorReportController extends Controller
{
    public function store(Request $request, VendorStore $vendorStore): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'in:scam,fake_products,poor_service,ip_violation,other'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $report = $vendorStore->vendor->reports()->create([
            'user_id' => $request->user()?->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'],
        ]);

        $admins = User::role(['admin', 'super_admin'])->get();
        Notification::send($admins, new NewVendorReport($report));

        return back()->with('success', 'Thanks for reporting this — our team will review it.');
    }
}
