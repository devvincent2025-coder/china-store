<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a vendor configure their own shipping fee (flat, a percentage of the order, or both) — see
 * Vendor::calculateShippingFee() and App\Services\Checkout\CheckoutService for where this is
 * applied. VAT is deliberately NOT here: it's a single platform-wide rate an admin sets from
 * Filament\Pages\Settings (App\Models\Setting::vatRate()), the same for every vendor.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Vendor/Settings', [
            'vendor' => $request->user()->vendor,
            // Read-only display only — see the class doc comment above for why this isn't
            // editable here.
            'vatRatePercent' => Setting::vatRate(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shipping_fee_type' => ['required', 'in:fixed,percentage,both'],
            'shipping_fee_fixed_amount' => ['required_if:shipping_fee_type,fixed,both', 'nullable', 'numeric', 'min:0'],
            'shipping_fee_percentage' => ['required_if:shipping_fee_type,percentage,both', 'nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $request->user()->vendor->update([
            'shipping_fee_type' => $validated['shipping_fee_type'],
            'shipping_fee_fixed_amount' => (int) round(($validated['shipping_fee_fixed_amount'] ?? 0) * 100),
            'shipping_fee_percentage' => $validated['shipping_fee_percentage'] ?? 0,
        ]);

        return back()->with('success', 'Settings updated.');
    }

    /**
     * The right-hand "Store Preferences" column on the same page — deliberately a separate request
     * from update() above, since these fields (vacation mode, low stock threshold, notification
     * channels, auto-publish default) are unrelated to VAT/shipping and a vendor may reasonably
     * change one group without touching the other.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vacation_mode' => ['required', 'boolean'],
            'vacation_message' => ['nullable', 'string', 'max:255'],
            'low_stock_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'notify_new_order_email' => ['required', 'boolean'],
            'notify_new_order_push' => ['required', 'boolean'],
            'auto_publish_products' => ['required', 'boolean'],
            // Shown to buyers on the product page, this store's page, and their order details —
            // see resources/js/utils/deliveryEstimate.js. Both blank = no estimate shown at all.
            'delivery_days_min' => ['nullable', 'integer', 'min:1', 'max:60'],
            'delivery_days_max' => ['nullable', 'integer', 'min:1', 'max:60', 'gte:delivery_days_min'],
        ]);

        $request->user()->vendor->update($validated);

        return back()->with('success', 'Preferences updated.');
    }
}
