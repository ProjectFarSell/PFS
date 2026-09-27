<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_can_be_combined_and_preserve_decimal_prices(): void
    {
        $category = Category::factory()->create();
        $shop = Shop::factory()->create();
        $matching = Product::factory()->create([
            'category_id' => $category->id,
            'shop_id' => $shop->id,
            'name' => 'Exact decimal match',
            'price' => 199.95,
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'shop_id' => $shop->id,
            'name' => 'Outside price range',
            'price' => 200.00,
        ]);
        Product::factory()->create(['name' => 'Different shop and category', 'price' => 199.95]);

        $this->get(route('catalog.index', [
            'category' => $category->id,
            'shop' => [$shop->id],
            'price_min' => '199.95',
            'price_max' => '199.95',
        ]))->assertOk()
            ->assertSee($matching->name)
            ->assertDontSee('Outside price range')
            ->assertDontSee('Different shop and category')
            ->assertViewHas('products', fn ($products) => $products->total() === 1)
            ->assertViewHas('priceMin', '199.95')
            ->assertViewHas('priceMax', '199.95');
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $url = route('catalog.index');

        $this->from($url)->get(route('catalog.index', [
            'price_min' => '20.999',
            'price_max' => '10.00',
            'shop' => [999999],
        ]))->assertRedirect($url)
            ->assertSessionHasErrors(['price_min', 'price_max', 'shop.0']);
    }

    public function test_rating_control_is_hidden_until_product_reviews_exist(): void
    {
        $this->get(route('catalog.index'))->assertOk()
            ->assertDontSee('name="rating[]"', false);
    }
}
