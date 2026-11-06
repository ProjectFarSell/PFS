<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Support\Cart;

trait ShipmentScenario
{
    private function shipmentScenario(bool $variant = false): array
    {
        config(['notifications.enabled' => false]);
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'city' => 'Quezon City']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Scenario Shirt', 'price' => 500, 'stock' => 10, 'has_variants' => $variant]);
        $option = $variant ? $product->variants()->create(['sku' => 'SCEN-BKS1', 'options' => ['Size' => 'S', 'Color' => 'Black'], 'stock' => 10, 'is_active' => true]) : null;
        $address = Address::factory()->create(['user_id' => $buyer->id, 'city' => 'Quezon City']);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [Cart::lineKey($product->id, $option?->id) => 1]])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $part = $order->fulfillments()->sole();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $rider = RiderProfile::create(['user_id' => User::factory()->create(['role' => UserRole::Rider])->id,
            'status' => 'approved', 'city' => 'Quezon City', 'vehicle_type' => 'motorcycle', 'license_no' => 'SCENARIO']);
        $this->scenarioAction($seller, $part, 'accept')->assertSessionHasNoErrors();
        $this->scenarioAction($seller, $part, 'ready', ['pickup_address' => '123 Shop Street', 'pickup_contact' => 'Seller 09171234567'])->assertSessionHasNoErrors();
        $this->scenarioAction($admin, $part, 'assign', ['rider_id' => $rider->id])->assertSessionHasNoErrors();
        $this->scenarioAction($rider->user, $part, 'pickup')->assertSessionHasNoErrors();

        return compact('buyer', 'seller', 'shop', 'product', 'option', 'address', 'order', 'part', 'admin', 'rider');
    }

    private function scenarioAction(User $actor, $part, string $action, array $data = [])
    {
        return $this->actingAs($actor)->from('/fulfillments')->post(route('fulfillments.update', $part), ['action' => $action, ...$data]);
    }

    private function failShipment(array $s, string $reason = 'unreachable'): void
    {
        $this->scenarioAction($s['rider']->user, $s['part'], 'fail', ['failure_reason' => $reason, 'contact_method' => 'phone',
            'exception_note' => 'Called twice at the delivery address; no answer.'])->assertSessionHasNoErrors();
    }

    private function returnShipment(array $s, string $disposition = 'restock'): void
    {
        $this->failShipment($s, 'buyer_wrong_variant');
        $this->scenarioAction($s['admin'], $s['part'], 'return_to_seller', ['exception_note' => 'Return to original shop; buyer requested another variant.'])->assertSessionHasNoErrors();
        $this->scenarioAction($s['seller'], $s['part'], 'receive_return', ['return_received' => 1, 'return_disposition' => $disposition,
            'exception_note' => 'All original items physically received and inspected.'])->assertSessionHasNoErrors();
    }

    private function completeShipment(array $s): void
    {
        $this->scenarioAction($s['rider']->user, $s['part'], 'deliver', ['delivery_note' => 'Handed directly to buyer.',
            'delivery_recipient_name' => 'Buyer', 'delivery_pin' => $s['part']->fresh()->delivery_pin, 'cod_collected' => 1])->assertSessionHasNoErrors();
        $this->scenarioAction($s['buyer'], $s['part'], 'complete')->assertSessionHasNoErrors();
    }
}
