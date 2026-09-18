<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_lists_only_active_shops_and_active_product_counts(): void
    {
        $active = Shop::factory()->create(['name' => 'Visible demo shop']);
        $inactive = Shop::factory()->create(['name' => 'Hidden demo shop', 'is_active' => false]);
        Product::factory()->create(['shop_id' => $active->id]);
        Product::factory()->create(['shop_id' => $active->id, 'is_active' => false]);

        $this->get(route('shops.index'))->assertOk()->assertSee($active->name)
            ->assertDontSee($inactive->name)->assertSee('1 product')
            ->assertSee(route('shops.show', $active), false)
            ->assertViewHas('shops', fn ($shops) => $shops->total() === 1 && $shops->first()->products_count === 1);
        $this->get(route('shops.show', $inactive))->assertNotFound();
    }

    public function test_shop_links_open_its_own_active_products_only(): void
    {
        $shop = Shop::factory()->create();
        $own = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Own listed item']);
        $hidden = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Unlisted item', 'is_active' => false]);
        $other = Product::factory()->create(['name' => 'Different shop item']);
        $this->get(route('shops.show', $shop))->assertOk()->assertSee($own->name)
            ->assertDontSee($hidden->name)->assertDontSee($other->name)
            ->assertSee(route('products.show', $own), false)->assertSee(route('shops.index'), false);
    }

    public function test_directory_paginates_in_alphabetical_order(): void
    {
        $shops = collect(range(1, 13))->map(fn ($number) => Shop::factory()->create(['name' => sprintf('Shop %02d', $number)]));
        $this->get(route('shops.index'))->assertOk()->assertSeeInOrder($shops->take(12)->pluck('name')->all())
            ->assertDontSee('Shop 13')->assertViewHas('shops', fn ($rows) => $rows->total() === 13 && $rows->count() === 12);
        $this->get(route('shops.index', ['page' => 2]))->assertOk()->assertSee('Shop 13')->assertDontSee('Shop 01');
    }

    public function test_empty_directory_and_empty_shop_have_helpful_messages(): void
    {
        $this->get(route('shops.index'))->assertOk()->assertSee('No shops are available yet.');
        $shop = Shop::factory()->create();
        $this->get(route('shops.show', $shop))->assertOk()->assertSee('This shop has no products available yet.');
    }

    public function test_desktop_mobile_and_footer_shops_links_target_the_directory(): void
    {
        $response = $this->get(route('catalog.index'))->assertOk();
        $this->assertSame(3, substr_count($response->getContent(), 'href="'.route('shops.index').'"'));
    }
}
