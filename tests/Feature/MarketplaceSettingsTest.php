<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\MarketplaceSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShipmentSettlement;
use App\Models\User;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Orders\MarketplaceSettings;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class MarketplaceSettingsTest extends TestCase
{
    use RefreshDatabase, ShipmentScenario;

    public function test_settings_are_audited_and_old_orders_keep_their_fee_snapshots(): void
    {
        $s = $this->shipmentScenario();
        $this->actingAs($s['admin'])->get(route('admin.marketplace-settings.edit'))->assertOk();
        $before = MarketplaceSetting::findOrFail(1)->version;
        $this->put(route('admin.marketplace-settings.update'), ['commission_percent' => '10.00', 'delivery_fee' => '60.01', 'payout_hold_days' => 8, 'version' => $before])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('marketplace_settings', ['id' => 1, 'commission_bps' => 1000, 'delivery_fee_cents' => 6001, 'payout_hold_days' => 8, 'version' => $before + 1]);
        $this->assertDatabaseHas('security_audit_events', ['action' => 'marketplace_settings.updated', 'actor_id' => $s['admin']->id]);
        $this->assertSame(800, $s['order']->fresh()->fee_policy['commission_bps']);
        $this->assertSame(800, $s['part']->fresh()->fee_policy['commission_bps']);
        $this->completeShipment($s);
        $this->assertSame(4000, (int) ShipmentSettlement::sole()->commission_cents);
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$s['product']->id => 1]])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $new = Order::where('user_id', $buyer->id)->sole();
        $this->assertSame('60.01', $new->shipping_fee);
        $this->assertSame(1000, $new->fee_policy['commission_bps']);
        $this->assertSame(1000, $new->fulfillments()->sole()->fee_policy['commission_bps']);
    }

    public function test_setting_bounds_and_stale_versions_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $values = ['commission_bps' => 800, 'delivery_fee_cents' => 4900, 'payout_hold_days' => 7, 'version' => 1];
        foreach (['commission_bps' => 10001, 'delivery_fee_cents' => 100001, 'payout_hold_days' => 91] as $key => $bad) {
            try {
                app(MarketplaceSettings::class)->update($admin, [...$values, $key => $bad]);
                $this->fail('Out-of-range setting accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($key, $exception->errors());
            }
        }
        app(MarketplaceSettings::class)->update($admin, $values);
        $this->put(route('admin.marketplace-settings.update'), ['commission_percent' => '9', 'delivery_fee' => '49', 'payout_hold_days' => 7, 'version' => 1])->assertSessionHasErrors('version');
        $this->assertSame(2, MarketplaceSetting::findOrFail(1)->version);
        $this->put(route('admin.marketplace-settings.update'), ['commission_percent' => '8.125', 'delivery_fee' => '49', 'payout_hold_days' => 7, 'version' => 2])->assertSessionHasErrors('commission_percent');
    }

    public function test_mobile_quote_is_stale_after_a_settings_change_even_if_delivery_price_is_unchanged(): void
    {
        $buyer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['stock' => 3]);
        $token = app(MobileAuthentication::class)->issue($buyer, 'buyer')['access_token'];
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 1, 'cart_version' => 0], $headers)->assertOk();
        $quote = $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 1], $headers)->assertCreated()->json('data.id');
        app(MarketplaceSettings::class)->update($admin, ['commission_bps' => 900, 'delivery_fee_cents' => 4900, 'payout_hold_days' => 7, 'version' => 1]);
        $this->postJson('/api/v1/buyer/orders', ['quote_id' => $quote, 'cart_version' => 1, 'payment_method' => 'cod'], [...$headers, 'Idempotency-Key' => (string) Str::uuid()])->assertConflict()->assertJsonPath('code', 'quote_stale');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $product->fresh()->stock);
    }
}
