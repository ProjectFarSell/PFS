<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(string $payment = 'cod'): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller]);
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $otherShop = Shop::factory()->create(['user_id' => $otherSeller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 100, 'stock' => 5, 'name' => 'First shop item']);
        $otherProduct = Product::factory()->create(['shop_id' => $otherShop->id, 'price' => 200, 'stock' => 5, 'name' => 'Other shop private item']);
        $address = Address::factory()->create(['user_id' => $buyer->id, 'line1' => 'Private buyer address']);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 2, $otherProduct->id => 1]])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => $payment])->assertRedirect();
        $order = Order::query()->sole();
        $part = $order->fulfillments()->where('shop_id', $shop->id)->sole();
        $otherPart = $order->fulfillments()->where('shop_id', $otherShop->id)->sole();

        return compact('buyer', 'seller', 'otherSeller', 'product', 'otherProduct', 'order', 'part', 'otherPart');
    }

    private function rider(string $status = 'approved'): RiderProfile
    {
        return RiderProfile::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Rider])->id,
            'status' => $status, 'vehicle_type' => 'motorcycle', 'license_no' => 'TEST123', 'city' => 'Quezon City',
        ]);
    }

    private function action(User $user, Fulfillment $part, string $action, array $data = [])
    {
        if ($action === 'ready') {
            $data += ['pickup_address' => '123 Shop Street', 'pickup_contact' => 'Seller 09171234567'];
        }

        return $this->actingAs($user)->from('/fulfillments')->post(route('fulfillments.update', $part), ['action' => $action, ...$data]);
    }

    public function test_checkout_snapshots_shops_and_allocates_fee_without_rounding_loss(): void
    {
        $c = $this->checkout();
        $this->assertSame(2, $c['order']->fulfillments()->count());
        $this->assertEquals(49, $c['order']->fulfillments()->sum('shipping_fee'));
        $this->assertEquals(449, $c['order']->fulfillments->sum(fn ($p) => $p->amount()));
        $this->assertSame(1, $c['part']->items()->count());
        $this->assertSame(3, $c['product']->fresh()->stock);
        $this->actingAs($c['buyer'])->get(route('orders.show', $c['order']))->assertOk()->assertSee('Awaiting seller confirmation');
    }

    public function test_rejection_releases_only_own_stock_once_and_waives_its_fee(): void
    {
        $c = $this->checkout();
        $this->action($c['seller'], $c['part'], 'reject', ['reason' => 'Damaged item'])->assertSessionHasNoErrors();
        $this->assertSame(5, $c['product']->fresh()->stock);
        $this->assertSame(4, $c['otherProduct']->fresh()->stock);
        $this->assertSame(0.0, $c['part']->fresh()->amount());
        $this->assertSame(OrderStatus::PendingPayment, $c['order']->fresh()->status);
        $this->assertNull($c['order']->fresh()->stock_released_at);
        $this->action($c['seller'], $c['part'], 'reject', ['reason' => 'Repeated'])->assertSessionHasErrors('fulfillment');
        $this->assertSame(5, $c['product']->fresh()->stock);
        $this->assertSame(2, $c['part']->events()->count());
        $this->actingAs($c['buyer'])->get(route('orders.show', $c['order']))->assertSee('Damaged item');
        $this->action($c['otherSeller'], $c['otherPart'], 'reject', ['reason' => 'Unavailable'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Cancelled, $c['order']->fresh()->status);
        $this->assertNotNull($c['order']->fresh()->stock_released_at);
    }

    public function test_cod_flow_requires_collection_and_buyer_confirmation(): void
    {
        $c = $this->checkout();
        $rider = $this->rider();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->action($c['otherSeller'], $c['otherPart'], 'reject', ['reason' => 'Unavailable']);
        $this->action($c['seller'], $c['part'], 'accept')->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Confirmed, $c['order']->fresh()->status);
        $this->action($c['seller'], $c['part'], 'ready')->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/fulfillments?status=ready')->assertOk()->assertSee('Assign shipment');
        $this->action($admin, $c['part'], 'assign', ['rider_id' => $rider->id])->assertSessionHasNoErrors();
        $this->actingAs($rider->user)->get('/rider/dashboard')->assertOk()->assertSee($c['order']->number);
        $this->actingAs($rider->user)->get('/fulfillments')->assertOk()->assertSee('224.50')->assertSee('Confirm pickup')->assertSee('123 Shop Street');
        $this->action($rider->user, $c['part'], 'pickup')->assertSessionHasNoErrors();
        $this->action($rider->user, $c['part'], 'deliver', ['delivery_note' => 'Handed to buyer'])->assertSessionHasErrors('cod_collected');
        $this->assertSame(FulfillmentStatus::InTransit, $c['part']->fresh()->status);
        $this->action($rider->user, $c['part'], 'deliver', ['delivery_note' => 'Handed to buyer', 'cod_collected' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull($c['part']->fresh()->cod_collected_at);
        $this->action($rider->user, $c['part'], 'deliver', ['delivery_note' => 'Duplicate', 'cod_collected' => 1])->assertSessionHasErrors('fulfillment');
        $this->actingAs($rider->user)->get('/fulfillments')->assertDontSee('Private buyer address');
        $this->actingAs($c['buyer'])->get(route('orders.show', $c['order']))->assertSee('Confirm received');
        $this->action($c['buyer'], $c['part'], 'complete')->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Completed, $c['order']->fresh()->status);
        $this->action($c['buyer'], $c['part'], 'complete')->assertSessionHasErrors('fulfillment');
        $this->assertSame(7, $c['part']->events()->count());
    }

    public function test_wrong_roles_and_other_shops_cannot_mutate_shipments(): void
    {
        $c = $this->checkout();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->action($c['otherSeller'], $c['part'], 'accept')->assertForbidden();
        $this->action($c['buyer'], $c['part'], 'accept')->assertForbidden();
        $this->action($admin, $c['part'], 'accept')->assertForbidden();
        $this->action($c['seller'], $c['part'], 'assign', ['rider_id' => $this->rider()->id])->assertForbidden();
        $this->actingAs($c['seller'])->get('/fulfillments')->assertOk()->assertSee('First shop item')->assertDontSee('Other shop private item')->assertDontSee('Private buyer address');
        $this->actingAs($c['buyer'])->get('/fulfillments')->assertForbidden();
        $this->assertSame(FulfillmentStatus::Pending, $c['part']->fresh()->status);
    }

    public function test_invalid_or_stale_steps_and_unapproved_riders_are_blocked(): void
    {
        $c = $this->checkout();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $rider = $this->rider('pending');
        $this->action($c['seller'], $c['part'], 'ready')->assertSessionHasErrors('fulfillment');
        $this->action($admin, $c['part'], 'assign', ['rider_id' => $rider->id])->assertSessionHasErrors('fulfillment');
        $this->action($c['seller'], $c['part'], 'reject')->assertSessionHasErrors('reason');
        $this->action($c['seller'], $c['part'], 'accept')->assertSessionHasNoErrors();
        $this->action($c['seller'], $c['part'], 'accept')->assertSessionHasErrors('fulfillment');
        $this->actingAs($c['seller'])->post(route('fulfillments.update', $c['part']), ['action' => 'ready'])->assertSessionHasErrors(['pickup_address', 'pickup_contact']);
        $this->action($c['seller'], $c['part'], 'ready')->assertSessionHasNoErrors();
        $this->action($admin, $c['part'], 'assign', ['rider_id' => $rider->id])->assertSessionHasErrors('rider_id');
        $this->actingAs($rider->user)->get('/fulfillments')->assertForbidden();
        $rider->update(['status' => 'approved']);
        $this->action($admin, $c['part'], 'assign', ['rider_id' => $rider->id])->assertSessionHasNoErrors();
        $this->action($admin, $c['part'], 'assign', ['rider_id' => $rider->id])->assertSessionHasErrors('fulfillment');
        $this->action($this->rider()->user, $c['part'], 'pickup')->assertForbidden();
        $rider->update(['status' => 'suspended']);
        $this->action($rider->user, $c['part'], 'pickup')->assertForbidden();
        $this->action($c['buyer'], $c['part'], 'complete')->assertSessionHasErrors('fulfillment');
    }

    public function test_demo_prepaid_delivery_does_not_record_cash_and_siblings_progress_independently(): void
    {
        $c = $this->checkout('gateway_stub');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $rider = $this->rider();
        $this->action($c['seller'], $c['part'], 'accept');
        $this->action($c['seller'], $c['part'], 'ready');
        $this->action($admin, $c['part'], 'assign', ['rider_id' => $rider->id]);
        $this->action($rider->user, $c['part'], 'pickup');
        $this->action($rider->user, $c['part'], 'deliver', ['delivery_note' => 'Received by buyer'])->assertSessionHasNoErrors();
        $this->assertNull($c['part']->fresh()->cod_collected_at);
        $this->action(User::factory()->create(), $c['part'], 'complete')->assertForbidden();
        $this->action($admin, $c['part'], 'complete')->assertForbidden();
        $this->action($c['buyer'], $c['part'], 'complete')->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Paid, $c['order']->fresh()->status);
        $this->assertSame(FulfillmentStatus::Pending, $c['otherPart']->fresh()->status);
    }
}
