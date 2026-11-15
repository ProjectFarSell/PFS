<?php

namespace Tests\Feature;

use App\Enums\HandlingFlag;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\MobileViews;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class ProductShippingDetailsTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase, ShipmentScenario;

    private function seller(): User
    {
        $seller = User::factory()->seller()->create();
        Shop::factory()->create(['user_id' => $seller->id]);

        return $seller;
    }

    private function payload(array $extra = []): array
    {
        return ['name' => 'Glass vase', 'description' => 'A boxed vase.', 'category_id' => Category::factory()->create()->id,
            'price' => '500.00', 'stock' => 5, 'is_active' => 1, ...$extra];
    }

    private function details(): array
    {
        return ['length_cm' => '20.25', 'width_cm' => '15.50', 'height_cm' => '30.00', 'weight_kg' => '1.125', 'handling_flags' => ['fragile', 'keep_upright']];
    }

    public function test_create_edit_clear_and_omitted_fields_preserve_optional_semantics(): void
    {
        $seller = $this->seller();
        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()->assertSee('Shipping details (optional)');
        $data = $this->payload();
        $this->post(route('seller.products.store'), $data)->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->assertSame(array_fill_keys(array_keys($this->details()), null), $product->shippingDetails());
        $sku = $product->sku;
        $this->put(route('seller.products.update', $product), [...$data, ...$this->details(), 'version' => $product->editVersion()])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame($this->details(), $product->shippingDetails());
        $this->assertSame($sku, $product->sku);
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('value="1.125"', false)->assertSee('value="fragile" checked', false);
        $this->put(route('seller.products.update', $product), [...$data, 'version' => $product->editVersion()])->assertSessionHasNoErrors();
        $this->assertSame($this->details(), $product->fresh()->shippingDetails());
        $this->put(route('seller.products.update', $product), [...$data, 'version' => $product->fresh()->editVersion(), 'shipping_details_present' => 1,
            'length_cm' => '', 'width_cm' => '', 'height_cm' => '', 'weight_kg' => ''])->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->weight_kg);
        $this->assertSame([], $product->fresh()->handling_flags);
        $this->post(route('seller.products.store'), [...$data, ...$this->details()])->assertSessionHasNoErrors();
        $this->assertSame($this->details(), Product::latest('id')->first()->shippingDetails());
    }

    public function test_identical_create_and_edit_validation_rejects_invalid_numbers_and_flags(): void
    {
        $seller = $this->seller();
        $data = $this->payload();
        $product = Product::factory()->create(['shop_id' => $seller->shop->id, ...$this->details()]);
        $badInputs = [
            ['length_cm' => -1], ['width_cm' => 0], ['height_cm' => 1000.01], ['weight_kg' => 1000.001],
            ['length_cm' => 0], ['width_cm' => -1], ['height_cm' => -1], ['height_cm' => 0], ['weight_kg' => -1], ['weight_kg' => 0],
            ['length_cm' => '10 cm'], ['weight_kg' => 'heavy'], ['length_cm' => '0.001'], ['weight_kg' => '1.0001'],
            ['handling_flags' => 'fragile'], ['handling_flags' => ['unknown']], ['handling_flags' => ['fragile', 'fragile']],
            ['handling_flags' => ['x' => 'fragile']], ['handling_flags' => [null]], ['handling_flags' => [['fragile']]],
        ];
        foreach ($badInputs as $bad) {
            // Each case tests validation independently of the shared write-rate limit.
            $this->travel(1)->minutes();
            $field = array_key_first($bad);
            $this->actingAs($seller)->post(route('seller.products.store'), [...$data, ...$bad])->assertSessionHasErrors();
            $this->put(route('seller.products.update', $product), [...$data, ...$bad, 'version' => $product->fresh()->editVersion()])->assertSessionHasErrors();
            $this->assertSame($this->details(), $product->fresh()->shippingDetails(), $field);
        }
        $this->assertDatabaseCount('products', 1);
    }

    public function test_partial_null_and_boundary_values_and_fixed_enum_labels(): void
    {
        $seller = $this->seller();
        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload(['length_cm' => '1000', 'weight_kg' => '0.001', 'handling_flags' => null]))->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->assertSame('1000.00', $product->length_cm);
        $this->assertSame('0.001', $product->weight_kg);
        $this->assertNull($product->width_cm);
        $this->assertNull($product->handling_flags);
        $this->assertSame(['fragile', 'perishable', 'liquid', 'keep_upright', 'keep_dry', 'keep_cold', 'flammable', 'heavy'], array_column(HandlingFlag::cases(), 'value'));
        $this->assertSame('Keep upright', HandlingFlag::KeepUpright->label());
    }

    public function test_validation_redirect_retains_measurements_and_unchecked_flags(): void
    {
        $seller = $this->seller();
        $product = Product::factory()->create(['shop_id' => $seller->shop->id, ...$this->details()]);
        $this->actingAs($seller)->from(route('seller.products.edit', $product))->put(route('seller.products.update', $product),
            [...$this->payload(), 'price' => -1, 'length_cm' => '40.50', 'weight_kg' => '2.375', 'shipping_details_present' => 1, 'version' => $product->editVersion()])->assertSessionHasErrors('price');
        $page = $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('value="40.50"', false)->assertSee('value="2.375"', false);
        $page->assertDontSee('value="fragile" checked', false);
    }

    public function test_checkout_snapshots_are_stable_in_admin_rider_and_mobile_views(): void
    {
        $seller = $this->seller();
        $product = Product::factory()->create(['shop_id' => $seller->shop->id, 'price' => 500, ...$this->details()]);
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 1]])->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertEquals($this->details(), $order->items()->sole()->shipping_details);
        $product->update(['weight_kg' => '99', 'handling_flags' => ['heavy']]);
        $this->assertSame('1.125', $order->items()->sole()->shipping_details['weight_kg']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('orders.show', $order))->assertOk()->assertSee('Fragile')->assertSee('Keep upright')->assertSee('1.125')->assertDontSee('Heavy');
        $part = $order->fulfillments()->sole();
        $this->actingAs($seller)->get(route('fulfillments.index'))->assertOk()->assertSee('Fragile');
        $api = MobileViews::shipment($part, false);
        $this->assertSame(1.125, $api['items'][0]['shipping_details']['weight_kg']);
        $this->assertSame(['fragile', 'keep_upright'], $api['items'][0]['shipping_details']['handling_flags']);
        $part->items()->update(['shipping_details' => null]);
        $part->update(['shipping_package' => null]);
        $this->assertNull(MobileViews::shipment($part->fresh(), false)['items'][0]['shipping_details']);
        $this->actingAs($admin)->get(route('orders.show', $order))->assertOk()->assertDontSee('Keep upright');
    }

    public function test_both_mobile_checkout_and_rider_job_use_the_purchase_snapshot(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'stock' => 3, ...$this->details()]);
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $headers = ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($buyer, 'buyer')['access_token']];
        $this->getJson('/api/v1/products/'.$product->public_id, $headers)->assertOk()->assertJsonPath('data.weight_kg', 1.125)->assertJsonPath('data.handling_flags', ['fragile', 'keep_upright']);
        $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 1, 'cart_version' => 0], $headers)->assertOk();
        $quote = $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 1], $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/buyer/orders', ['quote_id' => $quote, 'cart_version' => 1, 'payment_method' => 'cod'], [...$headers, 'Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()->assertJsonPath('data.shipments.0.items.0.shipping_details.length_cm', 20.25);
        Auth::shouldUse('web');
        $s = $this->shipmentScenario();
        $s['part']->items()->update(['shipping_details' => json_encode($this->details())]);
        $this->actingAs($s['rider']->user)->get(route('fulfillments.index'))->assertOk()->assertSee('Fragile')->assertSee('Keep upright');
        $this->get(route('rider.dashboard'))->assertOk()->assertSee('Fragile');
        $this->assertSame(['fragile', 'keep_upright'], MobileViews::shipment($s['part']->fresh(), true)['items'][0]['shipping_details']['handling_flags']);
    }

    public function test_seller_admin_badges_and_hidden_product_ownership_guards(): void
    {
        $seller = $this->seller();
        $product = Product::factory()->create(['shop_id' => $seller->shop->id, ...$this->details()]);
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee('Fragile')->assertSee('Keep upright');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.products.index'))->assertOk()->assertSee('Fragile')->assertDontSee('<<<<<<<');
        $product->forceFill(['admin_hidden_at' => now(), 'admin_hide_reason' => 'Under review', 'admin_hidden_by' => $admin->id])->save();
        $this->actingAs($seller)->put(route('seller.products.update', $product), [...$this->payload(), 'is_active' => 1, 'weight_kg' => 2, 'version' => $product->fresh()->editVersion()])->assertSessionHasErrors('is_active');
        $this->assertFalse($product->fresh()->is_active);
        $this->actingAs($this->seller())->put(route('seller.products.update', $product), [...$this->payload(), 'version' => $product->editVersion()])->assertNotFound();
        $this->assertSame('1.125', $product->fresh()->weight_kg);
    }
}
