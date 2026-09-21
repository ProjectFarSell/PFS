<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RiderApplicationController;
use App\Http\Controllers\Admin\SellerApplicationController;
use App\Http\Controllers\Auth\GuestSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ShopController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Rider\DashboardController as RiderDashboardController;
use App\Http\Controllers\Rider\RiderRegistrationController;
use App\Http\Controllers\Seller\ApplicationController as SellerApplication;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\ProductController as SellerProducts;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Support\Facades\Route;

// ── Portal / entry point ──────────────────────────────────────────────────────
// Browsing is public by default. Keep both route names/URLs compatible
// with existing links; neither requires a guest-session entry step.
Route::get('/', HomeController::class)->name('welcome');

// Marketplace home feed (public, including first-time visitors).
Route::get('/home', HomeController::class)->name('home');

// ── Public catalog ────────────────────────────────────────────────────────────
Route::get('/search', [ProductController::class, 'index'])->name('catalog.index');
Route::get('/p/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
Route::get('/shop/{shop}', [ShopController::class, 'show'])->name('shops.show');

// ── Cart (open to guests and authenticated users) ─────────────────────────────
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');

// ── Checkout & orders ─────────────────────────────────────────────────────────

// ── Guest-only auth routes ────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/guest', [GuestSessionController::class, 'store'])->name('guest.start');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/admin', DashboardController::class)
    ->middleware(['auth', EnsureUserHasRole::class.':admin'])
    ->name('admin.dashboard');

Route::middleware(['auth', EnsureUserHasRole::class.':admin'])->prefix('admin/riders')->name('admin.riders.')->group(function () {
    Route::get('/', [RiderApplicationController::class, 'index'])->name('index');
    Route::get('/{riderProfile}', [RiderApplicationController::class, 'show'])->name('show');
    Route::post('/{riderProfile}/review', [RiderApplicationController::class, 'review'])->name('review');
    Route::get('/{riderProfile}/documents/{document}', [RiderApplicationController::class, 'document'])->name('document');
});

Route::get('/seller', SellerDashboardController::class)
    ->middleware(['auth', EnsureUserHasRole::class.':seller'])
    ->name('seller.dashboard');

Route::middleware(['auth', EnsureUserHasRole::class.':seller'])->prefix('seller/products')->name('seller.products.')->group(function () {
    Route::get('/create', [SellerProducts::class, 'create'])->name('create');
    Route::post('/', [SellerProducts::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::get('/{product}/edit', [SellerProducts::class, 'edit'])->name('edit');
    Route::put('/{product}', [SellerProducts::class, 'update'])->middleware('throttle:30,1')->name('update');
});

Route::middleware(['auth', EnsureUserHasRole::class.':admin'])->prefix('admin/sellers')->name('admin.sellers.')->group(function () {
    Route::get('/', [SellerApplicationController::class, 'index'])->name('index');
    Route::get('/{application}', [SellerApplicationController::class, 'show'])->name('show');
    Route::post('/{application}/review', [SellerApplicationController::class, 'review'])->middleware('throttle:30,1')->name('review');
});

// ── Authenticated-only routes ─────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/seller/apply', [SellerApplication::class, 'show'])->name('seller.apply');
    Route::post('/seller/apply', [SellerApplication::class, 'store'])->middleware('throttle:10,1')->name('seller.apply.store');
    Route::get('/account/profile', [ProfileController::class, 'show'])->name('account.profile');
    Route::get('/account/profile/edit', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->middleware('throttle:10,1')->name('account.profile.update');
    Route::put('/account/profile/password', [ProfileController::class, 'password'])->middleware('throttle:10,1')->name('account.profile.password');
    Route::delete('/account/profile', [ProfileController::class, 'destroy'])->middleware('throttle:5,1')->name('account.profile.destroy');
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [CheckoutController::class, 'show'])->name('orders.show');

    Route::get('/rider/apply', [RiderRegistrationController::class, 'create'])->name('rider.register');
    Route::post('/rider/apply', [RiderRegistrationController::class, 'store']);
    Route::get('/rider/profile', [RiderRegistrationController::class, 'profile'])->name('rider.profile');
    Route::get('/rider/dashboard', RiderDashboardController::class)->name('rider.dashboard');

    Route::get('account/addresses/locations/provinces', [AddressController::class, 'provinces'])->name('account.addresses.locations.provinces');
    Route::get('account/addresses/locations/cities-municipalities', [AddressController::class, 'citiesMunicipalities'])->name('account.addresses.locations.cities-municipalities');
    Route::get('account/addresses/locations/barangays', [AddressController::class, 'barangays'])->name('account.addresses.locations.barangays');

    Route::resource('account/addresses', AddressController::class)
        ->except(['show'])
        ->names('account.addresses');
});
