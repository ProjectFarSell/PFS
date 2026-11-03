<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReleaseHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_have_security_headers_no_shared_cache_and_server_generated_request_ids(): void
    {
        $response = $this->withHeader('X-Request-ID', 'user-controlled')->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertNotSame('user-controlled', $response->headers->get('X-Request-ID'));
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_real_login_route_limits_attempts_and_malformed_identifiers_do_not_crash(): void
    {
        $this->postJson('/login', ['email' => ['bad'], 'password' => 'wrong'])->assertUnprocessable();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'throttle@example.test', 'password' => 'wrong'])->assertRedirect();
        }
        $this->postJson('/login', ['email' => 'throttle@example.test', 'password' => 'wrong'])->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_api_hides_inactive_shops_uses_effective_stock_and_the_bucket_image_url(): void
    {
        $hidden = Shop::factory()->create(['is_active' => false]);
        Product::factory()->create(['shop_id' => $hidden->id]);
        $shown = Product::factory()->create(['has_variants' => true, 'stock' => 999, 'image_path' => 'products/demo.jpg']);
        $shown->variants()->create(['sku' => 'TEST-BLKS', 'options' => 'Size: S, Color: Black', 'stock' => 3, 'is_active' => true]);
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shown->id)->assertJsonPath('data.0.stock', 3)
            ->assertJsonPath('data.0.image_url', $shown->imageUrl());
    }

    public function test_api_rejects_invalid_filters_and_accepts_a_maximum_without_a_minimum(): void
    {
        foreach ([['q' => ['bad']], ['price_min' => 'not-a-price'], ['price_min' => -1], ['price_min' => 30, 'price_max' => 20], ['page' => 0]] as $parameters) {
            $this->getJson('/api/v1/catalog?'.http_build_query($parameters))->assertUnprocessable();
        }
        $this->getJson('/api/v1/catalog?price_max=100')->assertOk();
        $this->get(route('catalog.index', ['price_max' => 100]))->assertOk();
    }

    public function test_forged_forwarded_headers_cannot_change_the_host_or_bypass_https(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://store.example.test', 'security.trusted_proxies' => []]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example'])->get('http://store.example.test/login')
            ->assertStatus(308)->assertRedirect('https://store.example.test/login');
        $this->get('https://attacker.example/login')->assertStatus(400);
    }

    public function test_only_an_explicit_proxy_can_supply_the_https_scheme(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://store.example.test', 'security.trusted_proxies' => ['127.0.0.1']]);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeader('X-Forwarded-Proto', 'https')->get('http://store.example.test/login')
            ->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_managed_host_suffix_does_not_implicitly_trust_unknown_proxies(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://pilot.on-forge.com', 'security.trusted_proxies' => []]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])->withHeader('X-Forwarded-Proto', 'https')->get('http://pilot.on-forge.com/login')
            ->assertStatus(308)->assertRedirect('https://pilot.on-forge.com/login');
    }

    public function test_production_demo_seed_is_blocked_before_any_accounts_are_created(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        try {
            app(DatabaseSeeder::class)->run();
            $this->fail('Production demo seeding should be blocked.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('must not be seeded', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_shop_query_count_does_not_grow_with_each_rendered_product(): void
    {
        $buyer = User::factory()->create();
        $shop = Shop::factory()->create();
        Product::factory()->create(['shop_id' => $shop->id]);
        $this->actingAs($buyer);
        DB::enableQueryLog();
        $this->get(route('shops.show', $shop))->assertOk();
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        Product::factory()->count(11)->create(['shop_id' => $shop->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('shops.show', $shop))->assertOk();
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($small + 3, $large, 'Shop cards must not issue a shop/category/save query for every product.');
    }
}
