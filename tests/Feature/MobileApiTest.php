<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Services\Mobile\MobileAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use ChecksMobileContract;
    use RefreshDatabase;

    private function token(User $user): string
    {
        return app(MobileAuthentication::class)->issue($user, $user->role->value)['access_token'];
    }

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->token($user), 'Accept' => 'application/json'];
    }

    public function test_login_roles_revocation_and_error_envelope(): void
    {
        config(['mobile.events_enabled' => false]);
        $buyer = User::factory()->create(['password' => 'Valid!Password123']);
        $response = $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'Valid!Password123', 'app' => 'buyer'])->assertOk()->assertJsonPath('data.user.role', 'buyer');
        $headers = ['Authorization' => 'Bearer '.$response->json('data.access_token')];
        $this->getJson('/api/v1/session', $headers)->assertOk()->assertJsonPath('data.id', $buyer->public_id);
        $this->getJson('/api/v1/rider/shipments', $headers)->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->postJson('/api/v1/auth/logout', [], $headers)->assertNoContent();
        auth()->forgetGuards();
        $this->getJson('/api/v1/session', $headers)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'wrong', 'app' => 'buyer'])->assertUnauthorized();
    }

    public function test_account_changes_expiry_and_pending_rider_boundaries(): void
    {
        $buyer = User::factory()->create();
        $headers = $this->headers($buyer);
        $buyer->forceFill(['security_version' => 1])->save();
        $this->getJson('/api/v1/session', $headers)->assertUnauthorized();
        auth()->forgetGuards();
        $headers = $this->headers($buyer->fresh());
        $this->travel(25)->hours();
        $this->getJson('/api/v1/session', $headers)->assertUnauthorized();
        $this->travelBack();
        auth()->forgetGuards();
        $rider = User::factory()->create(['role' => UserRole::Rider]);
        $headers = $this->headers($rider);
        $this->getJson('/api/v1/rider/status', $headers)->assertOk()->assertJsonPath('data.approval_status', 'missing');
        $this->getJson('/api/v1/rider/shipments', $headers)->assertForbidden();
        $this->getJson('/api/v1/buyer/orders', $headers)->assertForbidden();
    }

    public function test_public_products_keep_legacy_endpoint_compatible_and_hide_inactive_rows(): void
    {
        $product = Product::factory()->create(['price' => 199.95, 'stock' => 3]);
        Product::factory()->create(['is_active' => false]);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->public_id)->assertJsonPath('data.0.unit_price', 19995);
        $this->getJson('/api/v1/products/'.$product->public_id)->assertOk();
        $this->getJson('/api/v1/products/'.$product->id)->assertNotFound();
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonPath('data.0.id', $product->id)->assertJsonPath('data.0.price', 199.95);
    }

    public function test_cart_versions_stock_and_guest_merge_replay(): void
    {
        $buyer = User::factory()->create();
        $headers = $this->headers($buyer);
        $product = Product::factory()->create(['stock' => 3, 'price' => 100, 'sku' => 'TEST-BKS1']);
        $data = ['product_id' => $product->public_id, 'qty' => 2, 'cart_version' => 0];
        $this->postJson('/api/v1/buyer/cart/lines', $data, $headers)->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.subtotal', 20000);
        $this->postJson('/api/v1/buyer/cart/lines', $data, $headers)->assertConflict()->assertJsonPath('code', 'cart_stale');
        $data['cart_version'] = 1;
        $this->postJson('/api/v1/buyer/cart/lines', $data, $headers)->assertConflict()->assertJsonPath('code', 'stock_conflict');
        $merge = ['merge_id' => (string) Str::uuid(), 'cart_version' => 1, 'lines' => [['product_id' => $product->public_id, 'qty' => 1]]];
        $this->postJson('/api/v1/buyer/cart/merge', $merge, $headers)->assertOk()->assertJsonPath('data.cart.subtotal', 30000);
        $this->postJson('/api/v1/buyer/cart/merge', $merge, $headers)->assertOk()->assertJsonPath('data.cart.version', 2);
        $merge['lines'][0]['qty'] = 2;
        $this->postJson('/api/v1/buyer/cart/merge', $merge, $headers)->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_quote_order_replay_owner_scope_and_stale_stock(): void
    {
        config(['mobile.events_enabled' => false, 'notifications.enabled' => false]);
        $buyer = User::factory()->create();
        $headers = $this->headers($buyer);
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['stock' => 4, 'price' => 100, 'sku' => 'TEST-BKS1']);
        $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 2, 'cart_version' => 0], $headers)->assertOk();
        $quote = $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 1], $headers)->assertCreated()->assertJsonPath('data.total', 24900)->json('data.id');
        $data = ['quote_id' => $quote, 'cart_version' => 1, 'payment_method' => 'cod'];
        $headers['Idempotency-Key'] = (string) Str::uuid();
        $order = $this->postJson('/api/v1/buyer/orders', $data, $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/buyer/orders', $data, $headers)->assertCreated()->assertJsonPath('data.id', $order);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $product->fresh()->stock);
        auth()->forgetGuards();
        $other = $this->headers(User::factory()->create());
        $this->getJson('/api/v1/buyer/orders/'.$order, $other)->assertNotFound();
        $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 0], $other)->assertNotFound();
    }
}
