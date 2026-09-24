<?php

use App\Http\Controllers\Vendor\AdCampaignController;
use App\Http\Controllers\Vendor\BankAccountController;
use App\Http\Controllers\Vendor\BannerController;
use App\Http\Controllers\Vendor\CouponController;
use App\Http\Controllers\Vendor\DashboardController;
use App\Http\Controllers\Vendor\ExportController;
use App\Http\Controllers\Vendor\IdentityController;
use App\Http\Controllers\Vendor\InvoiceController;
use App\Http\Controllers\Vendor\OnboardingController;
use App\Http\Controllers\Vendor\OrderController;
use App\Http\Controllers\Vendor\PayoutController;
use App\Http\Controllers\Vendor\ProductController;
use App\Http\Controllers\Vendor\ProductImageController;
use App\Http\Controllers\Vendor\ProductOptionController;
use App\Http\Controllers\Vendor\ProductVariantController;
use App\Http\Controllers\Vendor\ProfileController;
use App\Http\Controllers\Vendor\SettingsController;
use App\Http\Controllers\Vendor\TranslateController;
use Illuminate\Support\Facades\Route;

// EnsureVendorRole additionally blocks vendors who haven't passed onboarding yet from reaching the
// dashboard/products/etc — only /vendor/onboarding stays reachable until the store is active.
Route::middleware(['auth', 'role:vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding/profile', [OnboardingController::class, 'updateProfile'])->name('onboarding.profile');
    Route::post('/onboarding/bank-details', [OnboardingController::class, 'submitBankDetails'])->name('onboarding.bank-details');

    // Available during onboarding too — vendors may draft product copy later; keep it under auth+role.
    Route::post('/translate', TranslateController::class)->name('translate');

    Route::middleware('vendor.active')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Product::getRouteKeyName() returns 'slug' for the buyer-facing /products/{product:slug}
        // URLs, but every vendor-side link/route uses the numeric id (see Vendor/Products/Index.jsx
        // etc) — {product:id} overrides the model's global route key just for this group, so
        // implicit binding doesn't try (and fail) to look the id up as a slug.
        Route::resource('products', ProductController::class)->except(['show'])->parameters(['products' => 'product:id']);
        Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate'])->name('products.bulk-update');
        Route::post('/products/{product:id}/images', [ProductImageController::class, 'store'])->name('products.images.store');
        Route::delete('/products/{product:id}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
        Route::patch('/products/{product:id}/images/{image}/primary', [ProductImageController::class, 'setPrimary'])->name('products.images.primary');

        // Packaging/color/etc option management — see ProductOptionSyncService for how one sync
        // call regenerates the whole variant matrix.
        Route::post('/products/{product:id}/options', [ProductOptionController::class, 'sync'])->name('products.options.sync');
        Route::patch('/products/{product:id}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update');
        Route::patch('/products/{product:id}/variants', [ProductVariantController::class, 'bulkUpdate'])->name('products.variants.bulk-update');
        Route::post('/products/{product:id}/variants/{variant}/tiers', [ProductVariantController::class, 'syncTiers'])->name('products.variants.tiers.sync');

        Route::resource('coupons', CouponController::class)->except(['show', 'create', 'edit']);
        Route::resource('banners', BannerController::class)->except(['show', 'create', 'edit']);

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{vendorOrder}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{vendorOrder}', [OrderController::class, 'update'])->name('orders.update');
        Route::post('/orders/{vendorOrder}/disputes/{dispute}/respond', [OrderController::class, 'respondToDispute'])->name('orders.disputes.respond');

        // Invoice::getRouteKeyName() stays the default 'id' here — only the public, unauthenticated
        // payment link (routes/web.php) resolves by public_token instead.
        Route::resource('invoices', InvoiceController::class)->parameters(['invoices' => 'invoice:id']);
        Route::post('/invoices/{invoice:id}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('/invoices/{invoice:id}/void', [InvoiceController::class, 'void'])->name('invoices.void');

        // VAT rate + shipping fee configuration — see SettingsController and Vendor::calculateVat()/
        // calculateShippingFee() for how these feed into checkout math.
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::patch('/settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');

        // Account profile: everything submitted during onboarding, plus the OTP-gated bank account
        // change request flow — see VendorBankChangeService for why this is a three-step process.
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile/bank-change/resolve', [BankAccountController::class, 'resolveAccount'])->name('profile.bank-change.resolve');
        Route::post('/profile/bank-change/request', [BankAccountController::class, 'requestChange'])->name('profile.bank-change.request');
        Route::post('/profile/bank-change/verify', [BankAccountController::class, 'verifyOtp'])->name('profile.bank-change.verify');

        Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');

        // PDF/Excel exports — ?format=pdf (default) or ?format=xlsx; see HandlesExports trait.
        Route::get('/exports/orders', [ExportController::class, 'orders'])->name('exports.orders');
        Route::get('/exports/products', [ExportController::class, 'products'])->name('exports.products');
        Route::get('/exports/invoices', [ExportController::class, 'invoices'])->name('exports.invoices');
        Route::get('/exports/report', [ExportController::class, 'report'])->name('exports.report');

        // KYC — see App\Services\Vendor\VendorIdentityService's class doc comment for why this is
        // separate from (and not required to complete) onboarding.
        Route::post('/identity/phone/send-otp', [IdentityController::class, 'sendPhoneOtp'])->name('identity.phone.send-otp');
        Route::post('/identity/phone/verify-otp', [IdentityController::class, 'verifyPhoneOtp'])->name('identity.phone.verify-otp');
        Route::post('/identity/document', [IdentityController::class, 'uploadDocument'])->name('identity.document');
        Route::post('/identity/celebration-seen', [IdentityController::class, 'markCelebrationSeen'])->name('identity.celebration-seen');

        // "Boost your store" advertising — see App\Services\Ads\AdCampaignService.
        Route::get('/ads', [AdCampaignController::class, 'index'])->name('ads.index');
        Route::get('/ads/create', [AdCampaignController::class, 'create'])->name('ads.create');
        Route::post('/ads', [AdCampaignController::class, 'store'])->name('ads.store');
        Route::get('/ads/{adCampaign}/callback', [AdCampaignController::class, 'callback'])->name('ads.callback');
        Route::post('/ads/{adCampaign}/pause', [AdCampaignController::class, 'pause'])->name('ads.pause');
        Route::post('/ads/{adCampaign}/resume', [AdCampaignController::class, 'resume'])->name('ads.resume');
    });
});
