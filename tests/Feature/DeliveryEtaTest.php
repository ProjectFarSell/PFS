<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\PsgcCityMunicipality;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryEtaTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $shopCityId): Product
    {
        $category = Category::factory()->create();
        $shop = Shop::factory()->create([
            'psgc_city_municipality_id' => $shopCityId,
            'is_active' => true,
        ]);

        return Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);
    }

    public function test_same_city_buyer_sees_two_to_four_hour_eta(): void
    {
        $city = PsgcCityMunicipality::factory()->create();
        $product = $this->makeProduct($city->id);

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Address::factory()->create([
            'user_id' => $buyer->id,
            'psgc_city_municipality_id' => $city->id,
            'is_default' => true,
        ]);

        $this->actingAs($buyer)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('2–4 hours');
    }

    public function test_different_city_buyer_sees_next_day_eta(): void
    {
        $shopCity = PsgcCityMunicipality::factory()->create();
        $buyerCity = PsgcCityMunicipality::factory()->create();
        $product = $this->makeProduct($shopCity->id);

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Address::factory()->create([
            'user_id' => $buyer->id,
            'psgc_city_municipality_id' => $buyerCity->id,
            'is_default' => true,
        ]);

        $this->actingAs($buyer)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Next day');
    }

    public function test_buyer_with_no_default_address_sees_no_eta_badge(): void
    {
        $city = PsgcCityMunicipality::factory()->create();
        $product = $this->makeProduct($city->id);

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        // No address created for this buyer

        $this->actingAs($buyer)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('Est. delivery');
    }

    public function test_guest_sees_no_eta_badge(): void
    {
        $city = PsgcCityMunicipality::factory()->create();
        $product = $this->makeProduct($city->id);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('Est. delivery');
    }
}
