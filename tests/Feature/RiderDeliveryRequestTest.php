<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderDeliveryRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rider(string $city = 'Quezon City', bool $available = true, string $status = 'approved'): RiderProfile
    {
        $profile = RiderProfile::create([
            'user_id' => User::factory()->rider()->create()->id,
            'status' => $status, 'city' => $city, 'vehicle_type' => 'motorcycle', 'license_no' => 'TEST',
        ]);
        $profile->forceFill(['is_available' => $available])->save();

        return $profile;
    }

    private function shipment(): Fulfillment
    {
        $shop = Shop::factory()->create(['user_id' => User::factory()->seller()->create()->id, 'city' => 'Quezon City']);
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'number' => fake()->unique()->uuid(),
            'status' => 'confirmed', 'payment_method' => 'cod', 'ship_to' => 'SECRET RECIPIENT ADDRESS',
            'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149,
        ]);

        return $order->fulfillments()->create([
            'shop_id' => $shop->id, 'shop_name' => $shop->name, 'status' => 'accepted', 'subtotal' => 100, 'shipping_fee' => 49,
        ]);
    }

    private function ready(Fulfillment $part): void
    {
        $this->actingAs($part->shop->owner)->post(route('fulfillments.update', $part), [
            'action' => 'ready', 'pickup_address' => 'SECRET PICKUP ADDRESS', 'pickup_contact' => 'SECRET PHONE',
        ])->assertSessionHasNoErrors();
    }

    private function claim(RiderProfile $rider, Fulfillment $part)
    {
        return $this->actingAs($rider->user)->from('/rider/dashboard')
            ->post(route('fulfillments.update', $part), ['action' => 'claim']);
    }

    public function test_ready_notifies_only_eligible_riders_and_feed_hides_private_details(): void
    {
        $rider = $this->rider('  quezon   CITY ');
        $offline = $this->rider(available: false);
        $otherCity = $this->rider('Manila');
        $pending = $this->rider(status: 'pending');
        $part = $this->shipment();
        $this->assertDatabaseCount('rider_delivery_requests', 0);
        $this->ready($part);
        $this->assertDatabaseCount('rider_delivery_requests', 1);
        $this->assertDatabaseHas('rider_delivery_requests', ['rider_id' => $rider->id, 'fulfillment_id' => $part->id]);
        $response = $this->actingAs($rider->user)->getJson(route('rider.delivery-requests'))->assertOk()->assertJsonPath('count', 1);
        $this->assertStringContainsString('Accept delivery request', $response->json('html'));
        $this->assertStringNotContainsString('SECRET', $response->json('html'));
        foreach ([$offline, $otherCity] as $profile) {
            $this->actingAs($profile->user)->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 0);
        }
        $this->actingAs($pending->user)->getJson(route('rider.delivery-requests'))->assertForbidden();
    }

    public function test_first_claim_wins_and_admin_cannot_overwrite_it(): void
    {
        $first = $this->rider();
        $second = $this->rider();
        $part = $this->shipment();
        $this->ready($part);
        $this->claim($first, $part)->assertSessionHasNoErrors();
        $this->claim($second, $part)->assertSessionHasErrors('fulfillment');
        $this->claim($first, $part)->assertSessionHasErrors('fulfillment');
        $this->assertSame($first->id, $part->fresh()->rider_id);
        $this->assertSame('assigned', $part->fresh()->status->value);
        $this->assertSame(2, $part->events()->count());
        $this->actingAs($second->user)->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 0);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->post(route('fulfillments.update', $part), ['action' => 'assign', 'rider_id' => $second->id])->assertSessionHasErrors('fulfillment');
        $this->actingAs($first->user)->get('/fulfillments')->assertSee('SECRET RECIPIENT ADDRESS');
    }

    public function test_availability_backfills_requests_and_offline_does_not_cancel_accepted_work(): void
    {
        $rider = $this->rider(available: false);
        $part = $this->shipment();
        $this->ready($part);
        $this->claim($rider, $part)->assertForbidden();
        $this->actingAs($rider->user)->post(route('rider.availability'), ['is_available' => 1])->assertSessionHasNoErrors();
        $this->post(route('rider.availability'), ['is_available' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rider_delivery_requests', 1);
        $this->claim($rider, $part)->assertSessionHasNoErrors();
        $this->post(route('rider.availability'), ['is_available' => 0])->assertSessionHasNoErrors();
        $this->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 0);
        $this->post(route('fulfillments.update', $part), ['action' => 'pickup'])->assertSessionHasNoErrors();
        $this->assertSame('in_transit', $part->fresh()->status->value);
    }

    public function test_claim_rechecks_area_approval_and_readiness(): void
    {
        $rider = $this->rider();
        $wrongCity = $this->rider('Manila');
        $part = $this->shipment();
        $this->claim($rider, $part)->assertSessionHasErrors('fulfillment');
        $this->ready($part);
        $this->claim($wrongCity, $part)->assertForbidden();
        $rider->update(['status' => 'suspended']);
        $this->claim($rider, $part)->assertForbidden();
        $this->post(route('rider.availability'), ['is_available' => 1])->assertForbidden();
        $this->actingAs($part->shop->owner)->post(route('fulfillments.update', $part), ['action' => 'claim'])->assertForbidden();
        $this->assertNull($part->fresh()->rider_id);
    }

    public function test_admin_fallback_removes_request_and_guests_cannot_access_feed(): void
    {
        $this->getJson(route('rider.delivery-requests'))->assertUnauthorized();
        $rider = $this->rider();
        $part = $this->shipment();
        $this->ready($part);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->post(route('fulfillments.update', $part), ['action' => 'assign', 'rider_id' => $rider->id])->assertSessionHasNoErrors();
        $this->actingAs($rider->user)->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 0);
    }

    public function test_seller_can_publish_legacy_ready_shipment_once(): void
    {
        $rider = $this->rider();
        $part = $this->shipment();
        $part->update(['status' => 'ready']);
        $data = ['action' => 'request_rider', 'pickup_address' => 'Pickup point', 'pickup_contact' => 'Seller phone'];
        $this->actingAs(User::factory()->seller()->create())->post(route('fulfillments.update', $part), $data)->assertForbidden();
        $this->actingAs($part->shop->owner)->get('/fulfillments')->assertSee('Request a rider');
        $this->post(route('fulfillments.update', $part), ['action' => 'request_rider'])->assertSessionHasErrors(['pickup_address', 'pickup_contact']);
        $this->post(route('fulfillments.update', $part), $data)->assertSessionHasNoErrors();
        $this->get('/fulfillments')->assertSee('waiting for acceptance');
        $this->post(route('fulfillments.update', $part), $data)->assertSessionHasErrors('fulfillment');
        $this->assertDatabaseCount('rider_delivery_requests', 1);
        $this->claim($rider, $part)->assertSessionHasNoErrors();
    }

    public function test_automatic_offer_prioritizes_lower_workload_and_decline_moves_to_next_rider(): void
    {
        $busy = $this->rider();
        $free = $this->rider();
        $existing = $this->shipment();
        $existing->update(['status' => 'assigned', 'rider_id' => $busy->id]);
        $part = $this->shipment();
        $this->ready($part);
        $this->actingAs($busy->user)->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 0);
        $this->claim($busy, $part)->assertSessionHasErrors('fulfillment');
        $this->actingAs($free->user)->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 1);
        $this->post(route('fulfillments.update', $part), ['action' => 'decline'])->assertSessionHasNoErrors();
        $this->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 0);
        $this->actingAs($busy->user)->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 1);
        $this->claim($busy, $part)->assertSessionHasNoErrors();
    }

    public function test_expired_offer_cannot_be_accepted_and_scheduler_tries_next_rider(): void
    {
        $first = $this->rider();
        $second = $this->rider();
        $part = $this->shipment();
        $this->ready($part);
        $this->travel(121)->seconds();
        $this->claim($first, $part)->assertSessionHasErrors('fulfillment');
        $this->artisan('riders:dispatch')->assertSuccessful();
        $this->actingAs($second->user)->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 1);
        $this->claim($second, $part)->assertSessionHasNoErrors();
        $this->travelBack();
    }

    public function test_going_offline_reroutes_offer_and_exhausted_pool_waits_for_admin(): void
    {
        $first = $this->rider();
        $second = $this->rider();
        $part = $this->shipment();
        $this->ready($part);
        $this->actingAs($first->user)->post(route('rider.availability'), ['is_available' => 0])->assertSessionHasNoErrors();
        $this->actingAs($second->user)->getJson(route('rider.delivery-requests'))->assertJsonPath('count', 1);
        $this->post(route('fulfillments.update', $part), ['action' => 'decline'])->assertSessionHasNoErrors();
        $this->artisan('riders:dispatch')->assertSuccessful();
        $this->assertNull($part->fresh()->rider_id);
        $this->assertSame('ready', $part->fresh()->status->value);
        $this->assertDatabaseCount('rider_delivery_requests', 2);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->post(route('fulfillments.update', $part), ['action' => 'assign', 'rider_id' => $second->id])->assertSessionHasNoErrors();
    }
}
