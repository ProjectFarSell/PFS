<?php

namespace Tests\Feature;

use App\Enums\PackagePreset;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\Mobile\MobileViews;
use App\Services\Orders\DeliveryRequestService;
use App\Services\Orders\DispatchVehicleEligibility;
use App\Services\Orders\FulfillmentService;
use App\Support\Cart;
use App\Support\ProductShippingDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VehicleDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['notifications.enabled' => false]);
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['stock' => 10, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10, 'weight_kg' => 50]);
        $product->shop->update(['city' => 'Quezon City']);
        $seller = $product->shop->owner;
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 1]])->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $part = Order::sole()->fulfillments()->sole();
        app(FulfillmentService::class)->act($part, $seller, 'accept', []);

        return compact('buyer', 'product', 'seller', 'admin', 'part');
    }

    private function rider(string $vehicle): RiderProfile
    {
        $rider = RiderProfile::create(['user_id' => User::factory()->create(['role' => 'rider'])->id, 'status' => 'approved', 'city' => 'Quezon City', 'vehicle_type' => $vehicle, 'license_no' => 'DEMO']);
        $rider->forceFill(['is_available' => true])->save();

        return $rider;
    }

    private function ready(array $s, array $extra = []): void
    {
        $this->actingAs($s['seller'])->post(route('fulfillments.update', $s['part']), ['action' => 'ready', 'pickup_address' => '123 Shop Street', 'pickup_contact' => 'Seller 09171234567', ...$extra])->assertSessionHasNoErrors();
    }

    public function test_only_capable_riders_receive_and_see_measured_offers(): void
    {
        $s = $this->fixture();
        $bike = $this->rider('motorcycle');
        $car = $this->rider('car');
        $van = $this->rider('van');
        $this->ready($s);
        $part = $s['part']->fresh();
        $this->assertSame('sedan_mpv', $part->required_vehicle_tier);
        $this->assertDatabaseMissing('rider_delivery_requests', ['fulfillment_id' => $part->id, 'rider_id' => $bike->id]);
        $this->assertDatabaseHas('rider_delivery_requests', ['fulfillment_id' => $part->id, 'rider_id' => $car->id]);
        $this->assertSame(0, app(DeliveryRequestService::class)->requests($bike)->count());
        $this->assertSame(1, app(DeliveryRequestService::class)->requests($car)->count());
        $this->assertTrue(app(DispatchVehicleEligibility::class)->allows($part, $van));
        $part->update(['required_vehicle_tier' => null]);
        $this->assertSame(0, app(DeliveryRequestService::class)->requests($bike)->count());
        $this->assertSame(1, app(DeliveryRequestService::class)->requests($car)->count());
        // Stale/tampered offers still cannot be claimed by an undersized vehicle.
        DB::table('rider_delivery_requests')->insert(['fulfillment_id' => $part->id, 'rider_id' => $bike->id, 'expires_at' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($bike->user)->post(route('fulfillments.update', $part), ['action' => 'claim'])->assertSessionHasErrors('rider_id');
        $this->actingAs($s['admin'])->post(route('fulfillments.update', $part), ['action' => 'assign', 'rider_id' => $bike->id])->assertSessionHasErrors('rider_id');
        $this->post(route('fulfillments.update', $part), ['action' => 'assign', 'rider_id' => $van->id])->assertSessionHasNoErrors();
        $this->assertSame($van->id, $part->fresh()->rider_id);
        $this->actingAs($van->user)->post(route('fulfillments.update', $part), ['action' => 'pickup'])->assertSessionHasNoErrors();
        $this->assertSame('in_transit', $part->fresh()->status->value);
        $this->post(route('fulfillments.update', $part), ['action' => 'deliver', 'delivery_recipient_name' => 'Buyer', 'delivery_note' => 'Handed over the full packed order.', 'delivery_pin' => $part->fresh()->delivery_pin, 'cod_collected' => 1])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $part->fresh()->status->value);
    }

    public function test_seller_upgrade_blocks_dispatch_until_admin_review_and_never_changes_price(): void
    {
        $s = $this->fixture();
        $car = $this->rider('car');
        $van = $this->rider('van');
        $this->ready($s, ['requested_vehicle_tier' => 'van_light_truck', 'vehicle_upgrade_reason' => 'Packed carton is too awkward for a sedan.']);
        $part = $s['part']->fresh();
        $this->assertTrue($part->vehicle_review_pending);
        $this->assertSame(0, DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)->count());
        $this->actingAs($s['admin'])->get(route('fulfillments.index'))->assertOk()->assertSee('Dispatch paused for Admin vehicle review.');
        $this->post(route('fulfillments.update', $part), ['action' => 'assign', 'rider_id' => $van->id])->assertSessionHasErrors('rider_id');
        $this->actingAs($s['seller'])->post(route('fulfillments.update', $part), ['action' => 'review_vehicle_upgrade', 'vehicle_upgrade_decision' => 'approved', 'vehicle_upgrade_reason' => 'Cannot self approve a request.'])->assertForbidden();
        $this->actingAs($s['admin'])->post(route('fulfillments.update', $part), ['action' => 'review_vehicle_upgrade', 'vehicle_upgrade_decision' => 'approved', 'vehicle_upgrade_reason' => 'Verified carton needs a larger vehicle.'])->assertSessionHasNoErrors();
        $part->refresh();
        $this->assertFalse($part->vehicle_review_pending);
        $this->assertSame('van_light_truck', $part->required_vehicle_tier);
        $this->assertSame('van_light_truck', MobileViews::shipment($part->fresh(), true)['required_vehicle_tier']);
        $this->assertFalse(app(DispatchVehicleEligibility::class)->allows($part, $car));
        $this->assertDatabaseHas('rider_delivery_requests', ['fulfillment_id' => $part->id, 'rider_id' => $van->id]);
        $this->assertSame('49.00', $part->order->shipping_fee);
        $this->assertDatabaseHas('security_audit_events', ['action' => 'shipment.vehicle_upgrade_approved', 'actor_id' => $s['admin']->id]);
    }

    public function test_late_request_invalidates_existing_offer_and_rejection_resumes_original_tier(): void
    {
        $s = $this->fixture();
        $car = $this->rider('car');
        $this->ready($s);
        $this->actingAs($s['seller'])->post(route('fulfillments.update', $s['part']), ['action' => 'request_vehicle_upgrade', 'requested_vehicle_tier' => 'van_light_truck', 'vehicle_upgrade_reason' => 'Actual packed shape needs a larger vehicle.'])->assertSessionHasNoErrors();
        $this->assertSame(0, app(DeliveryRequestService::class)->requests($car)->count());
        $this->actingAs($s['admin'])->post(route('fulfillments.update', $s['part']), ['action' => 'review_vehicle_upgrade', 'vehicle_upgrade_decision' => 'rejected', 'vehicle_upgrade_reason' => 'Reviewed packing and sedan capacity is sufficient.'])->assertSessionHasNoErrors();
        $this->assertSame('sedan_mpv', $s['part']->fresh()->required_vehicle_tier);
        $this->assertSame(1, app(DeliveryRequestService::class)->requests($car)->count());
        $this->actingAs($s['seller'])->post(route('fulfillments.update', $s['part']), ['action' => 'request_vehicle_upgrade', 'requested_vehicle_tier' => 'motorcycle', 'vehicle_upgrade_reason' => 'Attempt to downgrade the required vehicle.'])->assertSessionHasErrors('requested_vehicle_tier');
    }

    public function test_presets_remain_editable_and_use_valid_shared_measurements(): void
    {
        $s = $this->fixture();
        $page = $this->actingAs($s['seller'])->get(route('seller.products.edit', $s['product']))->assertOk();
        foreach (PackagePreset::cases() as $preset) {
            $page->assertSee($preset->label());
            $validator = validator($preset->measurements(), ProductShippingDetails::rules());
            $this->assertFalse($validator->fails());
        }
        $page->assertSee('setPreset')->assertSee('Presets are editable estimates.');
    }
}
