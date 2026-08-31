<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Auth\GuestSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\WelcomeController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ShopController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Rider\RiderRegistrationController;
use Illuminate\Support\Facades\Route;

// ── Portal / entry point ──────────────────────────────────────────────────────
// Guests see the login+register landing page; authenticated users are
// redirected straight to the marketplace home feed.
Route::get('/', WelcomeController::class)->name('welcome');

// ── Marketplace home feed (requires any session — guest or auth) ──────────────
Route::get('/home', HomeController::class)->name('home');

// ── Public catalog ────────────────────────────────────────────────────────────
Route::get('/search', [ProductController::class, 'index'])->name('catalog.index');
Route::get('/p/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/shop/{shop}', [ShopController::class, 'show'])->name('shops.show');

// ── Cart (open to guests and authenticated users) ─────────────────────────────
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');

// ── Checkout & orders ─────────────────────────────────────────────────────────
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/orders/{order}', [CheckoutController::class, 'show'])->name('orders.show');

// ── Guest-only auth routes ────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/guest', [GuestSessionController::class, 'store'])->name('guest.start');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ── Authenticated-only routes ─────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/rider/apply', [RiderRegistrationController::class, 'create'])->name('rider.register');
    Route::post('/rider/apply', [RiderRegistrationController::class, 'store']);
    Route::get('/rider/profile', [RiderRegistrationController::class, 'profile'])->name('rider.profile');

    Route::resource('account/addresses', AddressController::class)
        ->except(['show'])
        ->names('account.addresses');
});
