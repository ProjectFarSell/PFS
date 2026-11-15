<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SettlementAdjustment;
use App\Models\ShipmentSettlement;
use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Invoices\InvoiceGeneratorApi;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Orders\ReturnRequestService;
use App\Services\Orders\RiderCodBalance;
use App\Services\Orders\VoucherService;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase, ShipmentScenario;

    private function voucher(array $overrides = []): Voucher
    {
        return Voucher::create([...['code' => 'SAVE50', 'name' => 'Save fifty', 'type' => 'fixed', 'value' => 5000,
            'minimum_spend_cents' => 10000, 'per_buyer_limit' => 1, 'is_active' => true,
            'created_by' => User::factory()->create(['role' => 'admin'])->id], ...$overrides]);
    }

    private function purchase(Voucher $voucher, array $products = [], ?User $buyer = null): Order
    {
        $buyer ??= User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $products = $products ?: [Product::factory()->create(['price' => '500.00', 'stock' => 5])];
        $cart = [];
        foreach ($products as $product) {
            $cart[$product->id] = 1;
        }
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => $cart])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod', 'voucher_code' => $voucher->code])->assertSessionHasNoErrors();

        return $buyer->orders()->latest('id')->firstOrFail();
    }

    public function test_admin_and_seller_can_manage_only_their_authorized_vouchers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $fields = ['code' => 'new50', 'name' => 'New deal', 'type' => 'fixed', 'discount_value' => '50', 'minimum_spend' => '100', 'maximum_discount' => null,
            'per_buyer_limit' => 1, 'usage_limit' => 10, 'is_active' => 1];
        $this->actingAs($admin)->post(route('admin.vouchers.store'), $fields)->assertSessionHasNoErrors()->assertRedirect(route('admin.vouchers.index'));
        $platform = Voucher::where('code', 'NEW50')->sole();
        $this->assertNull($platform->shop_id);
        $this->get(route('admin.vouchers.index'))->assertOk()->assertSee('NEW50');
        $this->get(route('admin.vouchers.edit', $platform))->assertOk();
        $this->actingAs($seller)->post(route('seller.vouchers.store'), [...$fields, 'code' => 'shop50', 'shop_id' => 999])->assertSessionHasNoErrors();
        $own = Voucher::where('code', 'SHOP50')->sole();
        $this->assertSame($shop->id, $own->shop_id);
        $this->get(route('seller.vouchers.index'))->assertOk()->assertSee('SHOP50')->assertDontSee('NEW50');
        $this->get(route('seller.vouchers.edit', $platform))->assertNotFound();
        $this->put(route('seller.vouchers.update', $platform), [...$fields, 'version' => 1])->assertNotFound();
        $this->post(route('admin.vouchers.store'), $fields)->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('seller.vouchers.index'))->assertForbidden();
        $this->get(route('admin.vouchers.index'))->assertForbidden();
        $this->assertSame(2, DB::table('security_audit_events')->where('action', 'voucher.saved')->count());
    }

    public function test_admin_validation_and_optimistic_version_protect_edits(): void
    {
        $voucher = $this->voucher();
        $this->actingAs(User::findOrFail($voucher->created_by));
        $fields = ['code' => 'SAVE50', 'name' => 'Updated', 'type' => 'percentage', 'discount_value' => '10.25', 'minimum_spend' => '100', 'per_buyer_limit' => 1, 'is_active' => 1, 'version' => 1,
            'expires_at' => now()->addDay()->format('Y-m-d H:i:s')];
        $this->put(route('admin.vouchers.update', $voucher), [...$fields, 'discount_value' => '100.01'])->assertSessionHasErrors('discount_value');
        $this->put(route('admin.vouchers.update', $voucher), $fields)->assertSessionHasNoErrors();
        $this->assertSame(1025, $voucher->fresh()->value);
        $this->put(route('admin.vouchers.update', $voucher), $fields)->assertSessionHasErrors('version');
        $this->assertSame(2, $voucher->fresh()->version);
    }

    public function test_web_preview_discounted_cod_invoice_and_snapshot_are_consistent(): void
    {
        $voucher = $this->voucher();
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['price' => '500.00', 'stock' => 3]);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->post(route('checkout.voucher'), ['voucher_code' => ' save50 '])->assertSessionHasNoErrors();
        $this->get('/checkout')->assertOk()->assertSee('499.00')->assertSee('SAVE50');
        $this->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod', 'voucher_code' => 'SAVE50', 'discount_cents' => 999999])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame('499.00', $order->total);
        $this->assertSame(499.0, $order->fulfillments()->sole()->amount());
        $this->assertSame(5000, $order->items()->sole()->discount_cents);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $voucher->update(['value' => 1, 'is_active' => false]);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('SAVE50')->assertSee('50.00');
        $this->get(route('orders.print', ['order' => $order, 'document' => 'invoice']))->assertOk()->assertSee('499.00')->assertSee('SAVE50');
    }

    public function test_percentage_cap_free_shipping_and_non_stacking(): void
    {
        $v = $this->voucher(['type' => 'percentage', 'value' => 2500, 'maximum_discount_cents' => 6000]);
        $order = $this->purchase($v);
        $this->assertSame('489.00', $order->total);
        $ship = $this->voucher(['code' => 'SHIPFREE', 'type' => 'free_shipping', 'value' => 0]);
        $order = $this->purchase($ship);
        $this->assertSame('500.00', $order->total);
        $this->assertSame(4900, $order->shipping_discount_cents);
        $this->assertSame(500.0, $order->fulfillments()->sole()->amount());
        $this->actingAs($order->buyer)->withSession([Cart::SESSION_KEY => [$order->items()->sole()->product_id => 1]])
            ->post(route('checkout.voucher'), ['voucher_code' => 'SAVE50,SHIPFREE'])->assertSessionHasErrors('voucher_code');
    }

    public function test_invalid_expired_future_minimum_and_limit_fail_without_stock_or_redemption(): void
    {
        $v = $this->voucher(['usage_limit' => 1]);
        $product = Product::factory()->create(['price' => '500.00', 'stock' => 6]);
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        foreach ([['is_active' => false], ['is_active' => true, 'expires_at' => now()->subSecond()], ['expires_at' => null, 'starts_at' => now()->addDay()], ['starts_at' => null, 'minimum_spend_cents' => 60000]] as $edit) {
            $v->update($edit);
            $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 1]])
                ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod', 'voucher_code' => $v->code])->assertSessionHasErrors('voucher_code');
            $this->assertSame(6, $product->fresh()->stock);
            $this->assertDatabaseCount('orders', 0);
        }
        $v->update(['minimum_spend_cents' => 0]);
        $this->purchase($v, [$product], $buyer);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod', 'voucher_code' => $v->code])->assertSessionHasErrors('voucher_code');
        $this->assertDatabaseCount('voucher_redemptions', 1);
    }

    public function test_shop_voucher_applies_only_to_its_shop_and_cannot_spend_other_shop_subtotal(): void
    {
        $one = Product::factory()->create(['price' => '100.01']);
        $two = Product::factory()->create(['price' => '500']);
        $v = $this->voucher(['shop_id' => $one->shop_id, 'type' => 'percentage', 'value' => 1000]);
        $order = $this->purchase($v, [$one, $two]);
        $this->assertSame(1000, $order->discount_cents);
        $this->assertSame(1000, $order->fulfillments()->where('shop_id', $one->shop_id)->sole()->discount_cents);
        $this->assertSame(0, $order->fulfillments()->where('shop_id', $two->shop_id)->sole()->discount_cents);
        $this->assertEquals((float) $order->total, $order->fulfillments->sum(fn ($p) => $p->amount()));
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $v->update(['minimum_spend_cents' => 20000]);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$one->id => 1, $two->id => 1]])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod', 'voucher_code' => $v->code])->assertSessionHasErrors('voucher_code');
        $this->assertSame([1, 1, 0], app(VoucherService::class)->allocate(2, [1, 2, 0]));
    }

    public function test_platform_and_seller_funding_have_correct_payout_and_refund_accounting(): void
    {
        foreach (['farsell', 'seller'] as $funding) {
            $s = $this->shipmentScenario();
            $v = $this->voucher(['code' => 'SAVE-'.$funding, 'shop_id' => $funding === 'seller' ? $s['shop']->id : null]);
            $s['order'] = $this->purchase($v, [$s['product']], $s['buyer']);
            $s['part'] = $s['order']->fulfillments()->sole();
            $this->scenarioAction($s['seller'], $s['part'], 'accept')->assertSessionHasNoErrors();
            $this->scenarioAction($s['seller'], $s['part'], 'ready', ['pickup_address' => 'Shop street', 'pickup_contact' => 'Seller 09171234567'])->assertSessionHasNoErrors();
            $this->scenarioAction($s['admin'], $s['part'], 'assign', ['rider_id' => $s['rider']->id])->assertSessionHasNoErrors();
            $this->scenarioAction($s['rider']->user, $s['part'], 'pickup')->assertSessionHasNoErrors();
            $this->completeShipment($s);
            $settlement = ShipmentSettlement::where('fulfillment_id', $s['part']->id)->sole();
            $this->assertSame($funding === 'seller' ? 41400 : 46000, (int) $settlement->seller_cents);
            $this->assertSame($funding === 'seller' ? 0 : 5000, (int) $settlement->voucher_cents);
            $this->assertSame(49900, app(RiderCodBalance::class)->forRider($s['rider'])['collected_cents']);
            $item = $s['order']->items()->sole();
            $case = app(ReturnRequestService::class)->submit($item, $s['buyer'], ['quantity' => 1, 'reason' => 'wrong_item', 'details' => 'Return original item.']);
            $this->assertSame('450.00', $case->requested_amount);
            app(ReturnRequestService::class)->review($case, $s['admin'], ['action' => 'approved', 'approved_amount' => '450.00', 'return_required' => false, 'note' => 'Reviewed.']);
            app(ReturnRequestService::class)->review($case->fresh(), $s['admin'], ['action' => 'refunded', 'refund_reference' => 'CASH-RETURN', 'note' => 'Buyer reimbursed.']);
            $adjustment = SettlementAdjustment::where('return_request_id', $case->id)->sole();
            $this->assertSame(45000, (int) $adjustment->refund_cents);
            $this->assertSame((int) $settlement->seller_cents, (int) $adjustment->seller_debit_cents);
            $this->assertSame($funding === 'seller' ? 0 : 5000, (int) $adjustment->voucher_reversal_cents);
        }
    }

    public function test_mobile_quote_uses_single_voucher_and_replay_cannot_redeem_twice(): void
    {
        $v = $this->voucher();
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['price' => '500', 'stock' => 5]);
        $headers = ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($buyer, 'buyer')['access_token']];
        $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 1, 'cart_version' => 0], $headers)->assertOk();
        $quote = $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 1, 'voucher_code' => $v->code], $headers)
            ->assertCreated()->assertJsonPath('data.total', 49900)->assertJsonPath('data.discount', 5000)->json('data.id');
        $body = ['quote_id' => $quote, 'cart_version' => 1, 'payment_method' => 'cod'];
        $headers['Idempotency-Key'] = (string) Str::uuid();
        $this->postJson('/api/v1/buyer/orders', $body, $headers)->assertCreated()->assertJsonPath('data.total', 49900);
        $this->postJson('/api/v1/buyer/orders', $body, $headers)->assertCreated();
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_mobile_quote_rejects_voucher_edits_before_order_creation(): void
    {
        $v = $this->voucher();
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['price' => '500', 'stock' => 5]);
        $headers = ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($buyer, 'buyer')['access_token']];
        $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 1, 'cart_version' => 0], $headers)->assertOk();
        $quote = $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 1, 'voucher_code' => $v->code], $headers)->assertCreated()->json('data.id');
        $v->update(['is_active' => false, 'version' => 2]);
        $this->postJson('/api/v1/buyer/orders', ['quote_id' => $quote, 'cart_version' => 1, 'payment_method' => 'cod'], [...$headers, 'Idempotency-Key' => (string) Str::uuid()])->assertConflict()->assertJsonPath('code', 'quote_stale');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_per_buyer_limit_survives_cancellation_but_does_not_block_other_buyers(): void
    {
        $v = $this->voucher();
        $buyer = User::factory()->create();
        $product = Product::factory()->create(['price' => '500', 'stock' => 5]);
        $order = $this->purchase($v, [$product], $buyer);
        $this->post(route('orders.cancel', $order), ['reason' => 'Changed my purchase plans.'])->assertSessionHasNoErrors();
        $this->assertSame(0, $order->fresh()->billableDiscountCents());
        $address = $buyer->addresses()->firstOrFail();
        $this->withSession([Cart::SESSION_KEY => [$product->id => 1]])->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod', 'voucher_code' => $v->code])->assertSessionHasErrors('voucher_code');
        $this->purchase($v, [$product]);
        $this->assertDatabaseCount('voucher_redemptions', 2);
    }

    public function test_invoice_api_receives_exact_flat_discount_and_shipping(): void
    {
        Storage::fake('local');
        config(['services.invoice_generator.api_key' => 'dummy-test-key', 'services.invoice_generator.endpoint' => 'https://invoices.example.test/create']);
        Http::fake(['invoices.example.test/*' => Http::response('%PDF-1.4 voucher invoice', 200)]);
        $order = $this->purchase($this->voucher());
        $this->assertStringStartsWith('%PDF-', app(InvoiceGeneratorApi::class)->generate($order));
        Http::assertSent(fn ($request) => $request['discounts'] == 50 && $request['fields']['discounts'] === true && $request['shipping'] == 49);
    }

    public function test_shop_cannot_issue_shipping_vouchers_and_large_allocations_keep_exact_cents(): void
    {
        $seller = User::factory()->seller()->create();
        Shop::factory()->create(['user_id' => $seller->id]);
        $this->actingAs($seller)->post(route('seller.vouchers.store'), ['code' => 'SHIP', 'name' => 'Not allowed', 'type' => 'free_shipping', 'minimum_spend' => 0, 'per_buyer_limit' => 1, 'is_active' => 1])->assertSessionHasErrors('type');
        $this->assertSame([50000000, 50000000], app(VoucherService::class)->allocate(100000000, [99999999990000, 99999999990000]));
        $item = new OrderItem(['unit_price' => '10.00', 'qty' => 3]);
        $item->forceFill(['discount_cents' => 100]);
        $this->assertSame(966, $item->refundableCents(1));
        $this->assertSame(1933, $item->refundableCents(2));
        $this->assertSame(2900, $item->refundableCents(3));
    }

    public function test_unused_vouchers_can_be_deleted_only_by_authorized_roles_with_current_version(): void
    {
        $platform = $this->voucher();
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $own = $this->voucher(['code' => 'OWN50', 'shop_id' => $shop->id]);
        $foreign = $this->voucher(['code' => 'OTHER50', 'shop_id' => Shop::factory()->create()->id]);
        $this->actingAs(User::factory()->create())->delete(route('seller.vouchers.destroy', $own), ['version' => 1])->assertForbidden();
        $this->delete(route('admin.vouchers.destroy', $platform), ['version' => 1])->assertForbidden();
        $this->actingAs($seller)->delete(route('seller.vouchers.destroy', $platform), ['version' => 1])->assertNotFound();
        $this->delete(route('seller.vouchers.destroy', $foreign), ['version' => 1])->assertNotFound();
        $own->update(['version' => 2]);
        $this->delete(route('seller.vouchers.destroy', $own), ['version' => 1])->assertSessionHasErrors('version');
        $this->delete(route('seller.vouchers.destroy', $own), ['version' => 2])->assertRedirect(route('seller.vouchers.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('vouchers', ['id' => $own->id]);
        $this->assertDatabaseHas('security_audit_events', ['action' => 'voucher.deleted', 'actor_id' => $seller->id, 'target_id' => $own->id]);
        $this->actingAs(User::findOrFail($platform->created_by))->delete(route('admin.vouchers.destroy', $platform), ['version' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('vouchers', ['id' => $platform->id]);
    }

    public function test_used_voucher_deletion_is_rejected_even_from_a_stale_unused_list(): void
    {
        $voucher = $this->voucher();
        $admin = User::findOrFail($voucher->created_by);
        $this->actingAs($admin)->get(route('admin.vouchers.index'))->assertOk()->assertSee('Delete voucher');
        $order = $this->purchase($voucher);
        $this->actingAs($admin)->delete(route('admin.vouchers.destroy', $voucher), ['version' => 1])->assertSessionHasErrors('voucher');
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
        $this->assertDatabaseHas('voucher_redemptions', ['order_id' => $order->id, 'voucher_id' => $voucher->id]);
        $this->assertSame('499.00', $order->fresh()->total);
        $this->assertSame(0, DB::table('security_audit_events')->where('action', 'voucher.deleted')->count());
        $this->get(route('admin.vouchers.index'))->assertOk()->assertDontSee('Delete voucher')->assertSee('Disable to stop new uses.');
    }
}
