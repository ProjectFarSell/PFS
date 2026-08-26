<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('FarSell')
            ->assertSee('Continue as guest');
    }

    public function test_guest_can_add_to_cart(): void
    {
        $this->seed(DatabaseSeeder::class);

        $product = Product::query()->first();

        $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'qty' => 2,
        ])->assertRedirect();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_mobile_catalog_contract_is_public_and_versioned(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->getJson('/api/v1/catalog')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'price', 'shop', 'category']],
            ]);
    }
}
