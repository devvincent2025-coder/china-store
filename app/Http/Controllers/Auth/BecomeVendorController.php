<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Notifications\VendorApplicationReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets an already-logged-in BUYER become a vendor on the same account — same login, same email,
 * same order history, just a Vendor record and the 'vendor' role added on top. Nothing about this
 * account's buyer side changes: they keep shopping, tracking orders, and reviewing products
 * exactly as before, and can freely do both from here on (Jumia/Etsy/Amazon Seller Central all work
 * this way — one login, buy AND sell).
 *
 * Separate from Auth\VendorRegisteredUserController on purpose: that one is guest-only and creates
 * a brand new user (email/phone/password all collected fresh). Here, all of that already exists on
 * the authenticated user, so this only ever needs to collect what's genuinely vendor-specific —
 * currently just business_name. See OpenShop/Index.jsx for the form this backs.
 */
class BecomeVendorController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Idempotent: a user who's already a vendor (e.g. a second tab, or double-submit) just
        // goes to their dashboard instead of erroring or creating a duplicate vendor record.
        if ($user->isVendor()) {
            return redirect()->route('vendor.dashboard');
        }

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            // vendors.contact_phone is NOT NULL — every buyer who finished signup normally
            // (BuyerSignupService) already has a verified phone, so this is only ever actually
            // asked for on the rare account that somehow doesn't (e.g. older/imported data).
            'phone' => [$user->phone ? 'nullable' : 'required', 'string', 'max:30'],
        ]);

        $user->assignRole('vendor');

        $user->vendor()->create([
            'business_name' => $validated['business_name'],
            'contact_email' => $user->email,
            'contact_phone' => $user->phone ?: $validated['phone'],
            // Same admin-controlled default the guest vendor signup uses — see
            // Auth\VendorRegisteredUserController's matching line.
            'commission_rate' => Setting::get('default_commission_rate', 10.00),
        ]);

        $user->notify(new VendorApplicationReceived);

        return redirect()->route('vendor.onboarding')->with('justRegisteredVendor', true);
    }
}
