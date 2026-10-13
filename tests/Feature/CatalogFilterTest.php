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

    public function test_sort_by_price_asc_returns_products_ordered_by_price(): void
    {
        $shop = Shop::factory()->create();
        $category = Category::factory()->create();

        $expensive = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Expensive Product',
            'price' => 500.00,
        ]);
        $cheap = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Cheap Product',
            'price' => 10.00,
        ]);
        $mid = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Mid Product',
            'price' => 250.00,
        ]);

        $response = $this->get(route('catalog.index', ['sort' => 'price_asc']));
        $response->assertOk();

        $products = $response->viewData('products');
        $prices = $products->pluck('price')->map(fn ($p) => (float) $p)->values()->all();
        $sorted = $prices;
        sort($sorted);

        $this->assertEquals($sorted, $prices);
    }

    public function test_sort_by_price_desc_returns_products_ordered_descending(): void
    {
        $shop = Shop::factory()->create();
        $category = Category::factory()->create();

        Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Product A',
            'price' => 100.00,
        ]);
        Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Product B',
            'price' => 400.00,
        ]);
        Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Product C',
            'price' => 200.00,
        ]);

        $response = $this->get(route('catalog.index', ['sort' => 'price_desc']));
        $response->assertOk();

        $products = $response->viewData('products');
        $prices = $products->pluck('price')->map(fn ($p) => (float) $p)->values()->all();
        $sorted = $prices;
        rsort($sorted);

        $this->assertEquals($sorted, $prices);
    }

    public function test_fulltext_search_returns_matching_product(): void
    {
        $shop = Shop::factory()->create();
        $category = Category::factory()->create();

        $matching = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Mango Shake Special',
            'price' => 75.00,
        ]);
        Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Unrelated Item',
            'price' => 50.00,
        ]);

        $this->get(route('catalog.index', ['q' => 'Mango']))
            ->assertOk()
            ->assertSee($matching->name)
            ->assertDontSee('Unrelated Item');
    }
}
