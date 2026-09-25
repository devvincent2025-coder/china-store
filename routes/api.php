<?php

use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;

/*
|--------------------------------------------------------------------------
| Agent-facing API (v1)
|--------------------------------------------------------------------------
|
| This is the surface AI tools (an MCP server, a ChatGPT Custom GPT Action,
| a Gemini extension, ...) call on a buyer's behalf. It's a thin JSON layer
| over the same services the storefront itself uses (CartResolver,
| CheckoutService, TieredPricingService) — see docs/ai-ordering/README.md.
|
| Auth is a Laravel Passport OAuth2 access token (guard 'api', see
| config/auth.php), obtained through a normal login + consent screen —
| never a token the buyer copy/pastes. /oauth/authorize and /oauth/token
| are registered automatically by Passport's own service provider; there's
| nothing to define for them here.
*/

Route::prefix('v1')->name('api.v1.')->middleware('auth:api')->group(function () {
    Route::get('/me', [MeController::class, 'show']);

    Route::middleware(CheckToken::using('orders:place'))->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
        Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');

        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    });

    Route::middleware(CheckToken::using('orders:read'))->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
    });
});
