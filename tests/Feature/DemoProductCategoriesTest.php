<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Support\CatalogSections;
use Database\Seeders\DemoCatalogProductsSeeder;
use Database\Seeders\DemoProductCategoriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_categories_are_repeatable_and_preserve_other_product_data(): void
    {
        $old = Category::factory()->create(['name' => "Women's"]);
        $shop = Shop::factory()->create(['slug' => 'metro-surplus']);
        $this->seed(DemoCatalogProductsSeeder::class);
        $rows = json_decode(file_get_contents(database_path('seeders/demo-catalog-products.json')), true);
        $edited = Product::where('slug', $rows[1]['slug'])->sole();
        $edited->update(['stock' => 37, 'price' => '12.34', 'name' => 'Seller edited name']);
        $unrelated = Product::factory()->create(['shop_id' => $shop->id, 'image_path' => $edited->image_path, 'category_id' => $old->id]);
        $otherShop = Product::factory()->create(['image_path' => $edited->image_path, 'category_id' => $old->id]);
        $aga = Product::factory()->create(['image_path' => 'products/prod-24-ph-11134207-7rase-m6w744f3a959bd.jpg']);
        $aga->variants()->create(['sku' => 'AGAP-BKXLS', 'stock' => 19, 'is_active' => true]);
        $this->seed(DemoProductCategoriesSeeder::class);
        $this->seed(DemoProductCategoriesSeeder::class);

        foreach ($rows as $row) {
            $product = Product::where('slug', $row['slug'])->sole();
            $this->assertSame($row['category_slug'] ?? $old->slug, $product->category->slug);
        }
        $this->assertSame('trading-cards', $aga->fresh()->category->slug);
        $this->assertSame(19, $aga->variants()->sole()->stock);
        $this->assertSame(37, $edited->fresh()->stock);
        $this->assertSame('12.34', $edited->fresh()->price);
        $this->assertSame('Seller edited name', $edited->fresh()->name);
        $this->assertEquals($old->id, $unrelated->fresh()->category_id);
        $this->assertEquals($old->id, $otherShop->fresh()->category_id);
        $this->assertDatabaseCount('products', 27);
        $this->assertSame(4, Category::whereIn('slug', ['trading-cards', 'custom-keyboards', 'gaming-audio', 'figures-models'])->count());

        foreach (CatalogSections::links() as $link) {
            if (! isset($link['filter']['category'])) {
                continue;
            }
            $this->get(route('catalog.index', $link['filter']))->assertOk()
                ->assertViewHas('products', fn ($products) => $products->every(fn ($p) => $p->category_id === $link['filter']['category']));
        }
        $this->get(route('catalog.index'))->assertOk()
            ->assertSee(route('catalog.index', ['category' => $edited->category_id]), false);
    }
}
