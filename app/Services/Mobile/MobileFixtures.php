<?php

namespace App\Services\Mobile;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PsgcBarangay;
use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\CancelOrder;
use App\Services\Orders\FulfillmentService;
use App\Services\Orders\PlaceOrder;
use App\Support\PrivacyNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MobileFixtures
{
    public function seed(): array
    {
        if (! app()->environment(['local', 'testing', 'staging']) || (app()->environment('staging') && ! config('mobile.staging_fixtures'))) {
            throw new RuntimeException('Mobile fixtures are restricted to an explicitly enabled non-production test environment.');
        }
        $password = (string) config('mobile.fixture_password', 'password');
        if (app()->environment('staging') && (strlen($password) < 12 || $password === 'password')) {
            throw new RuntimeException('Configure a private staging fixture password first.');
        }
        $saved = collect(['notifications.enabled', 'mobile.events_enabled', 'release.beta_enabled', 'release.checkout_enabled'])->mapWithKeys(fn ($key) => [$key => config($key)])->all();
        config(['notifications.enabled' => false, 'mobile.events_enabled' => false, 'release.beta_enabled' => false, 'release.checkout_enabled' => true]);
        try {
            return DB::transaction(function () use ($password) {
                $accounts = [];
                foreach (['buyer' => UserRole::Buyer, 'rider' => UserRole::Rider, 'pending-rider' => UserRole::Rider, 'seller' => UserRole::Seller, 'admin' => UserRole::Admin] as $name => $role) {
                    $user = User::firstOrCreate(['email' => 'mobile-'.$name.'@farsell.test'], ['name' => 'Mobile QA '.ucwords(str_replace('-', ' ', $name)), 'password' => $password, 'role' => $role]);
                    if (! $user->wasRecentlyCreated && (! $user->is_demo_account || $user->role !== $role || $user->suspended_at)) {
                        throw new RuntimeException('A fixture identity is occupied by an existing non-fixture account.');
                    }
                    if ($user->wasRecentlyCreated) {
                        $user->forceFill(['is_demo_account' => true, 'privacy_accepted_at' => now(), 'privacy_notice_version' => PrivacyNotice::VERSION])->save();
                    }
                    $accounts[$name] = $user;
                }
                $region = PsgcRegion::firstOrCreate(['psgc_code' => '9999900000'], ['region_code' => '99', 'name' => 'Mobile QA Region (test only)']);
                $province = PsgcProvince::firstOrCreate(['psgc_code' => '9999901000'], ['psgc_region_id' => $region->id, 'province_code' => '999', 'name' => 'Mobile QA Province']);
                $city = PsgcCityMunicipality::firstOrCreate(['psgc_code' => '9999901100'], ['psgc_province_id' => $province->id, 'city_municipality_code' => '99999', 'name' => 'Mobile QA City']);
                $barangay = PsgcBarangay::firstOrCreate(['psgc_code' => '9999901101'], ['psgc_city_municipality_id' => $city->id, 'barangay_code' => '99999999', 'name' => 'Mobile QA Barangay']);
                $address = Address::firstOrCreate(['user_id' => $accounts['buyer']->id, 'label' => 'Mobile QA'], ['line1' => 'Test delivery address — not a real destination', 'city' => $city->name, 'region' => $region->name, 'psgc_region_id' => $region->id, 'psgc_province_id' => $province->id, 'psgc_city_municipality_id' => $city->id, 'psgc_barangay_id' => $barangay->id, 'is_default' => true]);
                $shop = Shop::firstOrCreate(['user_id' => $accounts['seller']->id], ['name' => 'Mobile QA Shop (test only)', 'slug' => 'mobile-qa-fixtures', 'city' => $city->name, 'psgc_city_municipality_id' => $city->id, 'is_active' => true]);
                $category = Category::firstOrCreate(['slug' => 'mobile-qa-fixtures'], ['name' => 'Mobile QA fixtures']);
                foreach (['rider' => 'approved', 'pending-rider' => 'pending'] as $name => $status) {
                    RiderProfile::firstOrCreate(['user_id' => $accounts[$name]->id], ['status' => $status, 'vehicle_type' => 'motorcycle', 'license_no' => 'QA-NOT-A-REAL-LICENSE', 'city' => $city->name, 'bio' => 'Synthetic mobile QA fixture. No real identity documents.']);
                }
                $rider = $accounts['rider']->riderProfile;
                $service = app(FulfillmentService::class);
                foreach (FulfillmentStatus::cases() as $index => $target) {
                    if (Order::where('user_id', $accounts['buyer']->id)->where('number', 'MOBQA-'.$target->value)->exists()) {
                        continue;
                    }
                    $product = Product::firstOrCreate(['slug' => 'mobile-qa-'.$target->value], ['shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'QA shipment: '.$target->value, 'sku' => 'MQA'.str_pad((string) $index, 5, '0', STR_PAD_LEFT), 'description' => 'Synthetic API testing item; not for real purchase.', 'price' => 100, 'stock' => 100, 'is_active' => true, 'has_variants' => false]);
                    $line = (object) ['product' => $product, 'variant' => null, 'variant_options' => [], 'qty' => 1, 'unit_price' => 100, 'line_total' => 100];
                    $order = app(PlaceOrder::class)->handle($accounts['buyer'], $address, collect([$line]), PaymentMethod::Cod);
                    $order->update(['number' => 'MOBQA-'.$target->value]);
                    $part = $order->fulfillments()->firstOrFail();
                    if ($target === FulfillmentStatus::Pending) {
                        continue;
                    }
                    if ($target === FulfillmentStatus::Cancelled) {
                        app(CancelOrder::class)->handle($order, $accounts['buyer'], 'QA fixture cancellation before dispatch.');

                        continue;
                    }
                    if ($target === FulfillmentStatus::Rejected) {
                        $service->act($part, $accounts['seller'], 'reject', ['reason' => 'QA fixture seller rejection.']);

                        continue;
                    }
                    $service->act($part, $accounts['seller'], 'accept', []);
                    if ($target === FulfillmentStatus::Accepted) {
                        continue;
                    }
                    $service->act($part, $accounts['seller'], 'ready', ['pickup_address' => 'Mobile QA pickup (not a real destination)', 'pickup_contact' => 'QA contact', 'delivery_method' => 'internal']);
                    if ($target === FulfillmentStatus::Ready) {
                        DB::table('rider_delivery_requests')->insert(['public_id' => (string) Str::uuid(), 'fulfillment_id' => $part->id, 'rider_id' => $rider->id, 'offered_at' => now(), 'expires_at' => now()->addMinutes(2), 'created_at' => now(), 'updated_at' => now()]);

                        continue;
                    }
                    $service->act($part, $accounts['admin'], 'assign', ['rider_id' => $rider->id]);
                    if ($target === FulfillmentStatus::Assigned) {
                        continue;
                    }
                    $service->act($part, $accounts['rider'], 'pickup', []);
                    if ($target === FulfillmentStatus::InTransit) {
                        continue;
                    }
                    if (in_array($target, [FulfillmentStatus::DeliveryFailed, FulfillmentStatus::Returning, FulfillmentStatus::Returned], true)) {
                        $service->act($part, $accounts['rider'], 'fail', ['failure_reason' => 'unreachable', 'contact_method' => 'phone', 'exception_note' => 'QA fixture failed contact attempt.']);
                        if ($target === FulfillmentStatus::DeliveryFailed) {
                            continue;
                        }
                        $service->act($part, $accounts['admin'], 'return_to_seller', ['exception_note' => 'QA fixture return instruction.']);
                        if ($target === FulfillmentStatus::Returning) {
                            continue;
                        }
                        $service->act($part, $accounts['seller'], 'receive_return', ['return_received' => true, 'return_disposition' => 'quarantine', 'exception_note' => 'QA fixture physical return received.']);

                        continue;
                    }
                    $service->act($part, $accounts['rider'], 'deliver', ['delivery_pin' => $part->fresh()->delivery_pin, 'delivery_recipient_name' => 'QA recipient', 'delivery_note' => 'Synthetic QA handover.', 'cod_collected' => true]);
                    if ($target === FulfillmentStatus::Completed) {
                        $service->act($part, $accounts['buyer'], 'complete', []);
                    }
                }
                $rider->forceFill(['is_available' => true])->save();

                return ['accounts' => array_map(fn ($user) => $user->email, $accounts), 'shipment_statuses' => Order::where('user_id', $accounts['buyer']->id)->where('number', 'like', 'MOBQA-%')->count()];
            }, 3);
        } finally {
            config($saved);
        }
    }
}
