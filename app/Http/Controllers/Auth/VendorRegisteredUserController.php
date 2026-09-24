<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\VendorApplicationReceived;
use App\Services\Cart\CartResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A dedicated vendor sign-up flow, separate from the buyer registration in
 * Auth\RegisteredUserController — this creates both the user account AND a bare vendor record so
 * the user can continue straight into the onboarding wizard (business profile, documents, bank).
 */
class VendorRegisteredUserController extends Controller
{
    public function __construct(private readonly CartResolver $carts)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/VendorRegister');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'business_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole('vendor');

        $user->vendor()->create([
            'business_name' => $validated['business_name'],
            'contact_email' => $validated['email'],
            'contact_phone' => $validated['phone'],
            // Platform-wide defaults an admin controls from Filament\Pages\Settings — falls back to
            // the vendors table's own DB column defaults (10%, 0%) if never explicitly set.
            'commission_rate' => Setting::get('default_commission_rate', 10.00),
            'vat_rate' => Setting::get('default_vat_rate', 0.00),
        ]);

        event(new Registered($user));
        $user->notify(new VendorApplicationReceived);

        Auth::login($user);
        $this->carts->mergeGuestCartIntoUser($request, $user);

        return redirect()->route('vendor.onboarding')->with('justRegisteredVendor', true);
    }
}
