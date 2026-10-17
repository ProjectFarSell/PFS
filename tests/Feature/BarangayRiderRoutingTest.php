<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PsgcBarangay;
use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use App\Models\RiderBarangayAssignment;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayRiderRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function barangay(string $name = 'Calios'): PsgcBarangay
    {
        $region = PsgcRegion::create(['psgc_code' => '0400000000', 'region_code' => '04', 'name' => 'Region IV-A']);
        $province = PsgcProvince::create(['psgc_region_id' => $region->id, 'psgc_code' => '0400000001', 'province_code' => '001', 'name' => 'Laguna']);
        $city = PsgcCityMunicipality::create(['psgc_province_id' => $province->id, 'psgc_code' => '0400000002', 'city_municipality_code' => '00001', 'name' => 'Santa Cruz']);

        return PsgcBarangay::create([
            'psgc_city_municipality_id' => $city->id,
            'psgc_code' => '0400000003',
            'barangay_code' => '00000001',
            'name' => $name,
        ]);
    }

    private function rider(): RiderProfile
    {
        $profile = RiderProfile::create([
            'user_id' => User::factory()->rider()->create()->id,
            'status' => 'approved',
            'city' => 'Santa Cruz',
            'vehicle_type' => 'motorcycle',
            'license_no' => 'TEST',
        ]);
        $profile->forceFill(['is_available' => true])->save();

        return $profile;
    }

    private function order(?Address $address = null): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'address_id' => $address?->id,
            'number' => fake()->unique()->uuid(),
            'status' => 'confirmed',
            'payment_method' => 'cod',
            'ship_to' => 'Buyer delivery address',
            'subtotal' => 100,
            'shipping_fee' => 49,
            'total' => 149,
        ]);
    }

    public function test_checkout_snapshots_the_delivery_barangay_on_each_fulfillment(): void
    {
        $barangay = $this->barangay();
        $buyer = User::factory()->create();
        $address = Address::create([
            'user_id' => $buyer->id,
            'label' => 'Home',
            'line1' => '1 Main Street',
            'city' => 'Santa Cruz',
            'psgc_barangay_id' => $barangay->id,
        ]);
        $shop = Shop::factory()->create(['user_id' => User::factory()->seller()->create()->id, 'city' => 'Santa Cruz']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'category_id' => Category::factory()->create()->id, 'price' => 100]);
        $order = $this->order($address);
        $order->update(['user_id' => $buyer->id]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        app(FulfillmentService::class)->createForOrder($order);

        $this->assertDatabaseHas('fulfillments', [
            'order_id' => $order->id,
            'delivery_barangay_id' => $barangay->id,
        ]);
    }

    public function test_ready_shipment_is_assigned_to_its_designated_barangay_rider(): void
    {
        $barangay = $this->barangay();
        $rider = $this->rider();
        RiderBarangayAssignment::create(['rider_profile_id' => $rider->id, 'psgc_barangay_id' => $barangay->id]);
        $shop = Shop::factory()->create(['user_id' => User::factory()->seller()->create()->id, 'city' => null]);
        $part = $this->order()->fulfillments()->create([
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'delivery_barangay_id' => $barangay->id,
            'status' => 'accepted',
            'subtotal' => 100,
            'shipping_fee' => 49,
        ]);

        $this->actingAs($shop->owner)->post(route('fulfillments.update', $part), [
            'action' => 'ready',
            'pickup_address' => 'Shop pickup point',
            'pickup_contact' => '09000000000',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fulfillments', [
            'id' => $part->id,
            'rider_id' => $rider->id,
            'status' => 'assigned',
        ]);
        $this->assertNull($part->fresh()->pickup_city);
        $this->assertDatabaseCount('rider_delivery_requests', 0);
        $this->assertSame($rider->id, $part->fresh()->rider_id);
    }

    public function test_unassigned_barangays_continue_to_use_the_existing_city_offer_flow(): void
    {
        $barangay = $this->barangay();
        $rider = $this->rider();
        $shop = Shop::factory()->create(['user_id' => User::factory()->seller()->create()->id, 'city' => 'Santa Cruz']);
        $part = $this->order()->fulfillments()->create([
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'delivery_barangay_id' => $barangay->id,
            'status' => 'accepted',
            'subtotal' => 100,
            'shipping_fee' => 49,
        ]);

        $this->actingAs($shop->owner)->post(route('fulfillments.update', $part), [
            'action' => 'ready',
            'pickup_address' => 'Shop pickup point',
            'pickup_contact' => '09000000000',
        ])->assertSessionHasNoErrors();

        $this->assertSame('ready', $part->fresh()->status->value);
        $this->assertDatabaseHas('rider_delivery_requests', [
            'rider_id' => $rider->id,
            'fulfillment_id' => $part->id,
        ]);
    }
}
