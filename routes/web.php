<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SavedItemController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BuyerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductInventoryController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\RiderApplicationController;
use App\Http\Controllers\Admin\SellerApplicationController;
use App\Http\Controllers\Auth\ContactVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\GuestSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ShopController;
use App\Http\Controllers\Chat\ConversationController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\FulfillmentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Rider\DashboardController as RiderDashboardController;
use App\Http\Controllers\Rider\DeliveryRequestController;
use App\Http\Controllers\Rider\RiderRegistrationController;
use App\Http\Controllers\Seller\ApplicationController as SellerApplication;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\ProductController as SellerProducts;
use App\Http\Middleware\EnsureBuyerOrGuest;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectPrivateRolesFromStorefront;
use Illuminate\Support\Facades\Route;

// ── Portal / entry point ──────────────────────────────────────────────────────
// Browsing is public by default. Keep both route names/URLs compatible
// with existing links; neither requires a guest-session entry step.
Route::middleware(RedirectPrivateRolesFromStorefront::class)->group(function () {
    Route::get('/', HomeController::class)->name('welcome');

    // Marketplace home feed (public, including first-time visitors).
    Route::get('/home', HomeController::class)->name('home');

    // ── Public catalog ────────────────────────────────────────────────────────────
    Route::get('/search', [ProductController::class, 'index'])->name('catalog.index');
    Route::get('/p/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
    Route::get('/shop/{shop}', [ShopController::class, 'show'])->name('shops.show');
});

// ── Cart (open to guests and authenticated users) ─────────────────────────────
Route::middleware(EnsureBuyerOrGuest::class)->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::post('/cart/buy-now', [CartController::class, 'buyNow'])->name('cart.buy-now');
    Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
});

// ── Checkout & orders ─────────────────────────────────────────────────────────

// ── Guest-only auth routes ────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/guest', [GuestSessionController::class, 'store'])->name('guest.start');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/fulfillments/{fulfillment}/proof-photo', [FulfillmentController::class, 'proofPhoto'])->middleware('auth')->name('fulfillments.proof-photo');

Route::middleware('auth')->prefix('/account/verify')->name('contact-verification.')->group(function () {
    Route::get('/', [ContactVerificationController::class, 'show'])->name('show');
    Route::post('/choose', [ContactVerificationController::class, 'choose'])->middleware('throttle:otp-send')->name('choose');
    Route::post('/{channel}/send', [ContactVerificationController::class, 'send'])->middleware('throttle:otp-send')->name('send');
    Route::post('/{channel}', [ContactVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('verify');
    Route::post('/phone/firebase', [ContactVerificationController::class, 'verifyFirebasePhone'])->middleware('throttle:10,1')->name('phone.firebase');
});

Route::get('/admin', DashboardController::class)
    ->middleware(['auth', EnsureUserHasRole::class.':admin'])
    ->name('admin.dashboard');

Route::get('/admin/dashboard/summary', [DashboardController::class, 'summary'])
    ->middleware(['auth', EnsureUserHasRole::class.':admin'])
    ->name('admin.dashboard.summary');

Route::get('/admin/buyers', [BuyerController::class, 'index'])
    ->middleware(['auth', EnsureUserHasRole::class.':admin'])
    ->name('admin.buyers.index');

Route::get('/admin/products', [ProductInventoryController::class, 'index'])
    ->middleware(['auth', EnsureUserHasRole::class.':admin'])
    ->name('admin.products.index');

Route::middleware(['auth', EnsureUserHasRole::class.':admin'])->prefix('admin/categories')->name('admin.categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::post('/', [CategoryController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::put('/{category}', [CategoryController::class, 'update'])->middleware('throttle:30,1')->name('update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('throttle:30,1')->name('destroy');
});

Route::get('/admin/partners', [PartnerController::class, 'index'])
    ->middleware(['auth', EnsureUserHasRole::class.':admin'])
    ->name('admin.partners.index');

Route::middleware(['auth', EnsureUserHasRole::class.':admin'])->prefix('admin/riders')->name('admin.riders.')->group(function () {
    Route::get('/', [RiderApplicationController::class, 'index'])->name('index');
    Route::get('/barangays', [RiderApplicationController::class, 'barangayAssignments'])->name('barangays.index');
    Route::get('/{riderProfile}', [RiderApplicationController::class, 'show'])->name('show');
    Route::post('/{riderProfile}/review', [RiderApplicationController::class, 'review'])->name('review');
    Route::post('/{riderProfile}/barangays', [RiderApplicationController::class, 'assignBarangay'])->name('barangays.store');
    Route::delete('/{riderProfile}/barangays/{assignment}', [RiderApplicationController::class, 'removeBarangay'])->name('barangays.destroy');
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
    Route::get('/chat', [ConversationController::class, 'index'])->middleware(EnsureUserHasRole::class.':buyer,seller')->name('chat.index');
    Route::get('/chat/widget/conversations', [ConversationController::class, 'widgetConversations'])->middleware(EnsureUserHasRole::class.':buyer,seller')->name('chat.widget.conversations');
    Route::post('/chat/products/{product}', [ConversationController::class, 'start'])->middleware([EnsureUserHasRole::class.':buyer', 'throttle:10,1'])->name('chat.start');
    Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->middleware(EnsureUserHasRole::class.':buyer,seller')->name('chat.show');
    Route::get('/chat/{conversation}/messages', [ConversationController::class, 'messages'])->middleware(EnsureUserHasRole::class.':buyer,seller')->name('chat.messages');
    Route::post('/chat/{conversation}/messages', [ConversationController::class, 'send'])->middleware([EnsureUserHasRole::class.':buyer,seller', 'throttle:30,1'])->name('chat.send');
    Route::post('/rider/availability', [DeliveryRequestController::class, 'availability'])->middleware([EnsureUserHasRole::class.':rider', 'throttle:30,1'])->name('rider.availability');
    Route::get('/rider/delivery-requests', [DeliveryRequestController::class, 'index'])->middleware([EnsureUserHasRole::class.':rider', 'throttle:30,1'])->name('rider.delivery-requests');
    Route::get('/fulfillments', [FulfillmentController::class, 'index'])->middleware(EnsureUserHasRole::class.':admin,seller,rider')->name('fulfillments.index');
    Route::post('/fulfillments/{fulfillment}', [FulfillmentController::class, 'update'])->middleware([EnsureUserHasRole::class.':admin,seller,rider,buyer', 'throttle:60,1'])->name('fulfillments.update');
    Route::get('/seller/apply/locations/provinces', [SellerApplication::class, 'provinces'])->middleware(EnsureUserHasRole::class.':buyer,seller')->name('seller.apply.locations.provinces');
    Route::get('/seller/apply/locations/cities-municipalities', [SellerApplication::class, 'citiesMunicipalities'])->middleware(EnsureUserHasRole::class.':buyer,seller')->name('seller.apply.locations.cities-municipalities');
    Route::get('/seller/apply', [SellerApplication::class, 'show'])->middleware(EnsureUserHasRole::class.':buyer,seller')->name('seller.apply');
    Route::post('/seller/apply', [SellerApplication::class, 'store'])->middleware([EnsureUserHasRole::class.':buyer,seller', 'throttle:10,1'])->name('seller.apply.store');
    Route::get('/account/profile', [ProfileController::class, 'show'])->name('account.profile');
    Route::get('/account/profile/edit', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->middleware('throttle:10,1')->name('account.profile.update');
    Route::put('/account/profile/password', [ProfileController::class, 'password'])->middleware('throttle:10,1')->name('account.profile.password');
    Route::delete('/account/profile', [ProfileController::class, 'destroy'])->middleware('throttle:5,1')->name('account.profile.destroy');
    Route::get('/checkout', [CheckoutController::class, 'create'])->middleware(EnsureUserHasRole::class.':buyer')->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware(EnsureUserHasRole::class.':buyer')->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->middleware(EnsureUserHasRole::class.':buyer')->name('orders.index');
    Route::get('/orders/{order}', [CheckoutController::class, 'show'])->middleware(EnsureUserHasRole::class.':buyer,admin')->name('orders.show');
    Route::get('/orders/{order}/print/{document}', [CheckoutController::class, 'print'])->middleware(EnsureUserHasRole::class.':buyer,admin')->whereIn('document', ['invoice', 'packing-slip'])->name('orders.print');

    Route::get('/account/saved', [SavedItemController::class, 'index'])->middleware(EnsureUserHasRole::class.':buyer')->name('account.saved.index');
    Route::post('/account/saved/{product}', [SavedItemController::class, 'store'])->middleware([EnsureUserHasRole::class.':buyer', 'throttle:30,1'])->name('account.saved.store');
    Route::delete('/account/saved/{product}', [SavedItemController::class, 'destroy'])->middleware([EnsureUserHasRole::class.':buyer', 'throttle:30,1'])->name('account.saved.destroy');

    Route::get('/rider/apply', [RiderRegistrationController::class, 'create'])->middleware(EnsureUserHasRole::class.':buyer')->name('rider.register');
    Route::post('/rider/apply', [RiderRegistrationController::class, 'store'])->middleware(EnsureUserHasRole::class.':buyer');
    Route::get('/rider/profile', [RiderRegistrationController::class, 'profile'])->middleware(EnsureUserHasRole::class.':buyer,rider')->name('rider.profile');
    Route::get('/rider/dashboard', RiderDashboardController::class)->middleware(EnsureUserHasRole::class.':rider')->name('rider.dashboard');

    Route::get('account/addresses/locations/provinces', [AddressController::class, 'provinces'])->middleware(EnsureUserHasRole::class.':buyer')->name('account.addresses.locations.provinces');
    Route::get('account/addresses/locations/cities-municipalities', [AddressController::class, 'citiesMunicipalities'])->middleware(EnsureUserHasRole::class.':buyer')->name('account.addresses.locations.cities-municipalities');
    Route::get('account/addresses/locations/barangays', [AddressController::class, 'barangays'])->middleware(EnsureUserHasRole::class.':buyer')->name('account.addresses.locations.barangays');

    Route::resource('account/addresses', AddressController::class)
        ->middleware(EnsureUserHasRole::class.':buyer')
        ->except(['show'])
        ->names('account.addresses');
});
