<?php

use App\Http\Controllers\Auth\GuestSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ShopController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Rider\RiderRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', [ProductController::class, 'index'])->name('catalog.index');
Route::get('/p/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/shop/{shop}', [ShopController::class, 'show'])->name('shops.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/orders/{order}', [CheckoutController::class, 'show'])->name('orders.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/guest', [GuestSessionController::class, 'store'])->name('guest.start');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/rider/apply', [RiderRegistrationController::class, 'create'])->name('rider.register');
    Route::post('/rider/apply', [RiderRegistrationController::class, 'store']);
    Route::get('/rider/profile', [RiderRegistrationController::class, 'profile'])->name('rider.profile');
});
