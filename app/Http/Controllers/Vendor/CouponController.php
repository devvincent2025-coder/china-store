<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Vendor/Coupons/Index', [
            // Redemptions are eager-loaded (not just counted) so the "View Redemptions" modal on
            // each coupon row can show exactly which orders used it — see CouponUsage, the
            // durable per-redemption log this reads from (independent of the coupon's own
            // used_count counter column).
            'coupons' => $request->user()->vendor->coupons()
                ->withCount('usages')
                ->with(['usages' => fn ($q) => $q->with(['user:id,name,email', 'vendorOrder.order:id,order_number'])->latest()])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $coupon = $request->user()->vendor->coupons()->create([
            ...$validated,
            'value' => $validated['type'] === 'flat' ? (int) round($validated['value'] * 100) : $validated['value'],
            'minimum_spend' => (int) round(($validated['minimum_spend'] ?? 0) * 100),
        ]);

        if (! empty($validated['product_ids'])) {
            $coupon->products()->sync($validated['product_ids']);
        }

        return back()->with('success', 'Coupon created.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('update', $coupon);

        $validated = $this->validated($request);

        $coupon->update([
            ...$validated,
            'value' => $validated['type'] === 'flat' ? (int) round($validated['value'] * 100) : $validated['value'],
            'minimum_spend' => (int) round(($validated['minimum_spend'] ?? 0) * 100),
        ]);

        if (isset($validated['product_ids'])) {
            $coupon->products()->sync($validated['product_ids']);
        }

        return back()->with('success', 'Coupon updated.');
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('delete', $coupon);

        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash'],
            'type' => ['required', 'in:percentage,flat'],
            'value' => ['required', 'numeric', 'min:0'],
            'minimum_spend' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);
    }
}
