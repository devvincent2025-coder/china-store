<?php

use App\Http\Controllers\Admin\ExportController as AdminExportController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\AdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EmailPreferenceController;
use App\Http\Controllers\ComparePricesController;
use App\Http\Controllers\FavouritesController;
use App\Http\Controllers\GoogleMerchantFeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageSearchController;
use App\Http\Controllers\SearchSuggestionController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\KidsController;
use App\Http\Controllers\OpenShopController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TrackOrderController;
use App\Http\Controllers\VendorReportController;
use App\Http\Controllers\VendorStoreController;
use App\Http\Controllers\OAuthDiscoveryController;
use App\Http\Controllers\Webhooks\KorapayWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

// RFC 8414 / RFC 9728 discovery for the AI-ordering OAuth server (see docs/ai-ordering/) — lets a
// spec-aware client (Claude's remote MCP connector) auto-configure itself instead of a human
// copying the /oauth/authorize and /oauth/token URLs in by hand.
Route::get('/.well-known/oauth-authorization-server', [OAuthDiscoveryController::class, 'authorizationServer']);
Route::get('/.well-known/oauth-protected-resource', [OAuthDiscoveryController::class, 'protectedResource']);

// SEO infrastructure — see App\Support\Seo\Seo's class doc comment for the overall approach.
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-static.xml', [SitemapController::class, 'staticPages'])->name('sitemap.static');
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-stores.xml', [SitemapController::class, 'stores'])->name('sitemap.stores');
Route::get('/sitemap-products-{page}.xml', [SitemapController::class, 'products'])->whereNumber('page')->name('sitemap.products');
Route::get('/feeds/google-merchant.xml', GoogleMerchantFeedController::class)->name('feeds.google-merchant');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::post('/search/image', ImageSearchController::class)->name('search.image');
Route::get('/search/suggestions', SearchSuggestionController::class)->name('search.suggestions');

// Cross-vendor price comparison — see App\Services\Product\PriceComparisonService for how "the
// same product" is matched across independently-listed vendor catalogs.
Route::get('/compare', [ComparePricesController::class, 'index'])->name('compare.index');

Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/kids', KidsController::class)->name('kids.index');

Route::get('/stores', [VendorStoreController::class, 'index'])->name('stores.index');
Route::get('/stores/{vendorStore:slug}', [VendorStoreController::class, 'show'])->name('vendors.show');

Route::get('/favourites', FavouritesController::class)->name('favourites.index');
Route::get('/open-a-shop', OpenShopController::class)->name('open-shop');

// Legal & policy pages — static content, linked from the storefront footer/header, checkout, and
// vendor onboarding/registration. See resources/js/Pages/Legal/*.jsx.
Route::get('/terms-and-conditions', fn () => Inertia::render('Legal/Terms'))->name('legal.terms');
Route::get('/privacy-policy', fn () => Inertia::render('Legal/PrivacyPolicy'))->name('legal.privacy');
Route::get('/return-policy', fn () => Inertia::render('Legal/ReturnPolicy', [
    'escrowReturnWindowDays' => (int) \App\Models\Setting::get('escrow_return_window_days', config('services.escrow.return_window_days')),
]))->name('legal.return-policy');
Route::get('/customer-protection', function () {
    $days = (int) \App\Models\Setting::get('escrow_return_window_days', config('services.escrow.return_window_days'));

    // Single source of truth for both the VISIBLE FAQ section on Legal/CustomerProtection.jsx and
    // its FAQPage JSON-LD — see Seo::faqJsonLd()'s doc comment on why these must match exactly.
    $faqs = [
        ['question' => 'How long is my money held in escrow?', 'answer' => "Until you confirm receipt, or {$days} days after delivery passes with no report from you — whichever comes first."],
        ['question' => "What happens if a vendor doesn't respond to my dispute?", 'answer' => 'The vendor has 48-72 hours to respond with their side and any evidence. If they miss that window, or the two of you can\'t agree, an admin reviews everything available and makes a final decision.'],
        ['question' => 'Can I get a replacement instead of a refund?', 'answer' => "Yes. If an admin decides a replacement is the right outcome, the vendor ships a genuine replacement and payment releases to them for it, instead of a refund."],
        ['question' => "What if I miss my return window?", 'answer' => "Once your return window passes without a report, the order is considered accepted and payment releases to the vendor automatically."],
        ['question' => 'Does escrow protect purchases made outside checkout?', 'answer' => 'No — escrow only covers orders paid for through ChinaInNigeria\'s own checkout, not any arrangement made directly with a vendor outside the platform.'],
    ];

    $request = request();

    return Inertia::render('Legal/CustomerProtection', [
        'escrowReturnWindowDays' => $days,
        'faqs' => $faqs,
        'seo' => \App\Support\Seo\Seo::generic(
            $request,
            'Buyer Protection & Escrow Guarantee',
            "Your money stays protected until you confirm you're happy — or your {$days}-day return window passes. See exactly how ChinaInNigeria's escrow and dispute process protects every order.",
            ['buyer protection nigeria', 'escrow guarantee', 'buyer protection guarantee', 'chinainnigeria escrow'],
            [\App\Support\Seo\Seo::faqJsonLd($faqs)],
        ),
    ]);
})->name('legal.customer-protection');
Route::get('/vendor-protection', fn () => Inertia::render('Legal/VendorProtection'))->name('legal.vendor-protection');

// Public order tracking — no login required, verified by order number + the email on the order's
// account. See TrackOrderController's class doc comment for how verification/session state works.
Route::get('/track-order', [TrackOrderController::class, 'index'])->name('track-order.index');
Route::post('/track-order', [TrackOrderController::class, 'store'])->name('track-order.store');
Route::get('/track-order/{orderNumber}', [TrackOrderController::class, 'show'])->name('track-order.show');

// Public, unauthenticated invoice payment pages — the customer a vendor invoices for an
// out-of-website sale may have no platform account at all, so these are reached via the invoice's
// own unguessable public_token rather than any auth-gated route.
Route::get('/invoices/{invoice:public_token}/pay', [InvoicePaymentController::class, 'show'])->name('invoices.pay.show');
Route::post('/invoices/{invoice:public_token}/pay', [InvoicePaymentController::class, 'store'])->name('invoices.pay.store');
Route::get('/invoices/{invoice:public_token}/pay/callback', [InvoicePaymentController::class, 'callback'])->name('invoices.pay.callback');

// Public ad tracking — no auth required, any buyer can visit a "Top Picks" card or generate a
// dwell-time sample. Deliberately generic path/param naming (no "ads"/"click" in the URL) and
// keyed by AdCampaign::token (opaque, not the raw numeric id) — see App\Http\Controllers\AdController.
Route::get('/go/{adCampaign:token}', [AdController::class, 'visit'])->name('go.visit');
Route::post('/go/{adCampaign:token}/ping', [AdController::class, 'ping'])->name('go.ping');

// Korapay signs every webhook payload — see KorapayWebhookController::hasValidSignature() — so this
// route is intentionally left outside the auth/CSRF-protected middleware group.
Route::post('/webhooks/korapay', KorapayWebhookController::class)->name('webhooks.korapay');

// Deliberately NOT behind 'auth' — a guest can add to cart, view it, and adjust quantities before
// ever logging in (see App\Services\Cart\CartResolver, which tracks a not-yet-authenticated cart
// via a cookie instead of a user_id). Checkout below is the actual point an account becomes
// required; Laravel's own guest-redirect there already remembers this as the "intended" URL, so
// logging in from there returns the buyer straight back to checkout.
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

// 'buyer.signup-complete' confines a buyer who hasn't finished phone verification to the home
// page (see EnsureBuyerCompletesSignup) — harmless for vendor/admin sessions, which never have
// User::needsPhoneVerification() true.
Route::middleware(['auth', 'buyer.signup-complete'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/callback', [CheckoutController::class, 'callback'])->name('checkout.callback');

    // Session-staged coupon apply/remove — see CheckoutController's SESSION_KEY doc comment for
    // why this is staged server-side rather than trusted from the final payment request.
    Route::post('/checkout/coupons', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupons.apply');
    Route::delete('/checkout/coupons/{vendorId}', [CheckoutController::class, 'removeCoupon'])->name('checkout.coupons.remove');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'downloadInvoice'])->name('orders.invoice');
    Route::post('/vendor-orders/{vendorOrder}/confirm-receipt', [OrderController::class, 'confirmReceipt'])->name('vendor-orders.confirm-receipt');
    Route::post('/vendor-orders/{vendorOrder}/disputes', [OrderController::class, 'storeDispute'])->name('vendor-orders.disputes.store');

    Route::post('/order-items/{orderItem}/review', [ReviewController::class, 'store'])->name('reviews.store');
    Route::post('/products/{product}/report', [ProductReportController::class, 'store'])->name('products.report');
    Route::post('/stores/{vendorStore:slug}/report', [VendorReportController::class, 'store'])->name('vendors.report');

    // Helpdesk — shared by both buyers and vendors, one ticket per issue. See
    // Http\Controllers\SupportTicketController's class doc comment.
    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');

    // In-app notifications — shared by buyers and vendors (a notification just belongs to a User).
    // Admins get the equivalent from Filament's own built-in notification bell instead.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Browser push subscriptions — see resources/js/utils/pushNotifications.js.
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::get('/dashboard', function () {
        return redirect()->route('home');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/default-address', [ProfileController::class, 'updateDefaultAddress'])->name('profile.default-address.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::delete('/profile/connected-apps/{token}', [ProfileController::class, 'destroyConnectedApp'])->name('profile.connected-apps.destroy');

    // Linked from every email footer — see resources/views/vendor/mail/html/footer.blade.php.
    Route::get('/account/email-preferences', [EmailPreferenceController::class, 'edit'])->name('email-preferences.edit');
    Route::patch('/account/email-preferences', [EmailPreferenceController::class, 'update'])->name('email-preferences.update');

    // Shared by every account type — buyer, vendor, admin.
    Route::post('/account/avatar', [AvatarController::class, 'store'])->name('avatar.store');
    Route::delete('/account/avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
});

// Both impersonation routes are guard-checked against 'admin' specifically (not the storefront's
// 'web' guard, and not Spatie's role middleware, which only ever looks at the DEFAULT guard) —
// see Http\Controllers\Admin\ImpersonationController's class doc comment for why the two guards
// being independent is what makes this whole flow simple and safe.
Route::middleware(['auth:admin'])->group(function () {
    Route::get('/admin-impersonate/{user}', [ImpersonationController::class, 'start'])->name('admin.impersonate.start');

    // Platform-wide PDF/Excel exports, linked from Filament resource header actions — see
    // Http\Controllers\Concerns\HandlesExports and Http\Controllers\Admin\ExportController.
    Route::get('/admin/exports/orders', [AdminExportController::class, 'orders'])->name('admin.exports.orders');
    Route::get('/admin/exports/products', [AdminExportController::class, 'products'])->name('admin.exports.products');
    Route::get('/admin/exports/invoices', [AdminExportController::class, 'invoices'])->name('admin.exports.invoices');
    Route::get('/admin/exports/report', [AdminExportController::class, 'report'])->name('admin.exports.report');
    Route::get('/admin/exports/google-merchant', [AdminExportController::class, 'googleMerchant'])->name('admin.exports.google-merchant');
});

// 'auth:web,admin' passes if EITHER guard has a session — stop() only needs the admin guard to be
// live, but during normal use the web guard (the impersonated account) is live too.
Route::middleware(['auth:web,admin'])->group(function () {
    Route::post('/admin-impersonate/stop', [ImpersonationController::class, 'stop'])->name('admin.impersonate.stop');
});

require __DIR__.'/auth.php';
require __DIR__.'/vendor.php';

// Guarded here, not just inside the file itself — the /dev/otps route must not exist at all
// outside local development, not merely be gated by middleware. See routes/dev.php.
if (app()->environment('local')) {
    require __DIR__.'/dev.php';
}
