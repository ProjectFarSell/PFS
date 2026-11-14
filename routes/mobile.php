<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BuyerFeaturesController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RealtimeController;
use App\Http\Controllers\Api\V1\RiderController;
use App\Http\Middleware\MobileAccess;
use Illuminate\Support\Facades\Route;

Route::name('api.mobile.')->middleware('throttle:mobile-api')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:mobile-login')->name('login');
    Route::post('auth/google', [BuyerFeaturesController::class, 'google'])->middleware('throttle:mobile-login')->name('google');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:signup')->name('register');
    Route::post('auth/challenges/send', [AuthController::class, 'send'])->middleware('throttle:mobile-challenge')->name('challenge.send');
    Route::post('auth/challenges/verify', [AuthController::class, 'verify'])->middleware('throttle:mobile-challenge')->name('challenge.verify');
    Route::get('products', [ProductController::class, 'index'])->name('products');
    Route::get('products/{product_id}', [ProductController::class, 'show'])->name('products.show');
    Route::get('products/{product_id}/reviews', [BuyerFeaturesController::class, 'reviews'])->name('reviews');
    Route::get('review-media/{media_id}', [BuyerFeaturesController::class, 'media'])->name('reviews.media');
    Route::get('locations/{level}', [AddressController::class, 'locations'])->whereIn('level', ['regions', 'provinces', 'cities', 'barangays'])->name('locations');

    Route::middleware(['auth:sanctum', MobileAccess::class])->group(function () {
        Route::get('session', [AuthController::class, 'session'])->name('session');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('auth/reauthenticate', [AuthController::class, 'reauthenticate'])->middleware('throttle:mobile-sensitive')->name('reauthenticate');
        Route::post('account/deletion-requests', [AccountController::class, 'deletion'])->middleware('throttle:mobile-sensitive')->name('deletion');
        Route::post('account/contact-verification/send', [AccountController::class, 'contactSend'])->middleware('throttle:mobile-sensitive')->name('contact.send');
        Route::post('account/contact-verification/verify', [AccountController::class, 'contactVerify'])->middleware('throttle:mobile-sensitive')->name('contact.verify');
        Route::put('devices/{installation_id}', [AccountController::class, 'device'])->name('devices.put');
        Route::delete('devices/{installation_id}', [AccountController::class, 'removeDevice'])->name('devices.delete');
        Route::post('broadcasting/auth', [RealtimeController::class, 'channel'])->name('broadcast');
        Route::prefix('buyer')->middleware(MobileAccess::class.':buyer')->group(function () {
            Route::get('conversations', [BuyerFeaturesController::class, 'conversations'])->name('conversations');
            Route::post('products/{product_id}/conversation', [BuyerFeaturesController::class, 'start'])->middleware('throttle:10,1')->name('conversations.start');
            Route::get('conversations/{conversation_id}/messages', [BuyerFeaturesController::class, 'messages'])->name('conversations.messages');
            Route::post('conversations/{conversation_id}/messages', [BuyerFeaturesController::class, 'send'])->middleware('throttle:30,1')->name('conversations.send');
            Route::get('shipments/{shipment_id}/feedback', [BuyerFeaturesController::class, 'feedback'])->name('feedback');
            Route::post('shipments/{shipment_id}/feedback', [BuyerFeaturesController::class, 'submitFeedback'])->middleware('throttle:10,1')->name('feedback.store');
            Route::post('orders/{order_id}/cancel', [BuyerFeaturesController::class, 'cancel'])->middleware('throttle:10,1')->name('orders.cancel');
            Route::get('returns', [BuyerFeaturesController::class, 'returns'])->name('returns');
            Route::post('order-items/{item_id}/returns', [BuyerFeaturesController::class, 'submitReturn'])->middleware('throttle:10,1')->name('returns.store');
            Route::get('returns/{return_id}/evidence', [BuyerFeaturesController::class, 'evidence'])->name('returns.evidence');
            Route::get('notifications', [BuyerFeaturesController::class, 'notifications'])->name('inbox');
            Route::post('notifications/{notification_id}/read', [BuyerFeaturesController::class, 'read'])->name('inbox.read');
            Route::get('cart', [CartController::class, 'index'])->name('cart');
            Route::post('cart/lines', [CartController::class, 'add'])->name('cart.add');
            Route::patch('cart/lines/{line_id}', [CartController::class, 'quantity'])->name('cart.quantity');
            Route::post('cart/merge', [CartController::class, 'merge'])->name('cart.merge');
            Route::get('addresses', [AddressController::class, 'index'])->name('addresses');
            Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::put('addresses/{address_id}', [AddressController::class, 'update'])->name('addresses.update');
            Route::delete('addresses/{address_id}', [AddressController::class, 'destroy'])->name('addresses.delete');
            Route::post('checkout/quotes', [OrderController::class, 'quote'])->name('quotes');
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
            Route::get('orders', [OrderController::class, 'index'])->name('orders');
            Route::get('orders/{order_id}', [OrderController::class, 'show'])->name('orders.show');
            Route::post('shipments/{shipment_id}/confirm-receipt', [OrderController::class, 'confirm'])->name('orders.confirm');
            Route::get('shipments/{shipment_id}/proof-photo', [OrderController::class, 'photo'])->name('orders.photo');
        });
        Route::get('rider/status', [RiderController::class, 'status'])->middleware(MobileAccess::class.':rider,no')->name('rider.status');
        Route::prefix('rider')->middleware(MobileAccess::class.':rider')->group(function () {
            Route::put('availability', [RiderController::class, 'availability'])->name('rider.availability');
            Route::get('offers', [RiderController::class, 'offers'])->name('rider.offers');
            Route::post('offers/{offer_id}/decision', [RiderController::class, 'decision'])->name('rider.decision');
            Route::get('shipments', [RiderController::class, 'shipments'])->name('rider.shipments');
            Route::get('shipments/{shipment_id}', [RiderController::class, 'shipment'])->name('rider.shipment');
            Route::post('shipments/{shipment_id}/pickup', [RiderController::class, 'transition'])->name('rider.pickup');
            Route::post('shipments/{shipment_id}/deliver', [RiderController::class, 'transition'])->name('rider.deliver');
            Route::post('shipments/{shipment_id}/failed-attempts', [RiderController::class, 'transition'])->name('rider.fail');
            Route::get('earnings', [RiderController::class, 'earnings'])->name('rider.earnings');
        });
    });
});
