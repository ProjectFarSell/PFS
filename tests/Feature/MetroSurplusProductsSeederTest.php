<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MetroSurplusProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetroSurplusProductsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_under_demo_seller_and_preserves_edits_on_rerun(): void
    {
        $seller = User::factory()->seller()->create(['email' => 'seller@farsell.test']);
        $shop = Shop::factory()->create(['slug' => 'metro-surplus', 'user_id' => $seller->id]);
        $this->seed(MetroSurplusProductsSeeder::class);
        $this->assertSame(238, $shop->products()->count());
        $product = $shop->products()->first();
        $product->update(['name' => 'Seller changed this', 'price' => '321.50', 'stock' => 2, 'is_active' => false]);
        $this->seed(MetroSurplusProductsSeeder::class);
        $this->assertSame(238, $shop->products()->count());
        $this->assertSame('Seller changed this', $product->fresh()->name);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame('321.50', $product->fresh()->price);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame(20, Product::whereHas('categories', fn ($q) => $q->where('slug', 'custom-keyboards'))->count());
        $this->assertSame(44, Product::whereHas('categories', fn ($q) => $q->where('slug', 'gaming-audio'))->count());
    }

    public function test_production_catalog_seeding_is_rejected(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        (new MetroSurplusProductsSeeder)->run();
    }
}
