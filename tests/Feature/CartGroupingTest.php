<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Cart;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_groups_two_shops_and_centavo_totals_match(): void
    {
        $first = Shop::factory()->create(['name' => 'First Shop']);
        $second = Shop::factory()->create(['name' => 'Second Shop']);
        $a = Product::factory()->create(['shop_id' => $first->id, 'name' => 'First Item', 'price' => '19.99', 'stock' => 10]);
        $b = Product::factory()->create(['shop_id' => $second->id, 'name' => 'Second Item', 'price' => '0.01', 'stock' => 10]);
        $response = $this->withSession([Cart::SESSION_KEY => [$a->id => 1, $b->id => 1]])->get(route('cart.index'));
        $response->assertOk()->assertSee('First Shop')->assertSee('Second Shop')->assertSee('19.99')->assertSee('0.01')->assertSee('20.00');
        $groups = $response->viewData('groups');
        $this->assertCount(2, $groups);
        $sum = $groups->reduce(fn ($total, $group) => $total->plus($group->subtotal), BigDecimal::zero());
        $this->assertSame((string) $sum->toScale(2), $response->viewData('subtotal'));
    }

    public function test_buyer_cart_with_one_shop_and_empty_cart(): void
    {
        $shop = Shop::factory()->create(['name' => 'Only Shop']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => '19.99', 'stock' => 5]);
        $this->actingAs(User::factory()->create())->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->get(route('cart.index'))->assertOk()->assertSee('Only Shop')->assertViewHas('groups', fn ($groups) => $groups->count() === 1);
        $this->withSession([Cart::SESSION_KEY => []])->get(route('cart.index'))->assertOk()->assertSee('Your cart is empty');
    }

    public function test_quantity_error_is_displayed_and_zero_removes_line(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $this->withSession([Cart::SESSION_KEY => [$product->id => 1]])->from(route('cart.index'))->followingRedirects()
            ->patch(route('cart.update', $product), ['qty' => 2])->assertSee('Only 1 units are currently available for this option.');
        $this->from(route('cart.index'))->patch(route('cart.update', $product), ['qty' => 0])->assertSessionHasNoErrors();
        $this->get(route('cart.index'))->assertSee('Your cart is empty');
    }

    public function test_fractional_prices_quantities_and_variant_overrides_have_exact_group_parity(): void
    {
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();
        $a = Product::factory()->create(['shop_id' => $first->id, 'price' => '19.99', 'stock' => 10]);
        $b = Product::factory()->create(['shop_id' => $second->id, 'price' => '0.01', 'stock' => 10]);
        $c = Product::factory()->create(['shop_id' => $second->id, 'price' => '33.33', 'stock' => 10]);
        $variantProduct = Product::factory()->create(['shop_id' => $first->id, 'price' => '99.00', 'stock' => 10, 'has_variants' => true]);
        $variant = $variantProduct->variants()->create(['sku' => 'PARITY01', 'options' => ['Size' => 'S'], 'price_override' => '0.10', 'stock' => 10, 'is_active' => true]);
        foreach ([null, User::factory()->create()] as $buyer) {
            if ($buyer) {
                $this->actingAs($buyer);
            }
            $response = $this->withSession([Cart::SESSION_KEY => [$a->id => 3, $b->id => 7, $c->id => 2, Cart::lineKey($variantProduct->id, $variant->id) => 3]])->get(route('cart.index'))->assertOk();
            $this->assertSame('127.00', $response->viewData('subtotal'));
            $sum = $response->viewData('groups')->reduce(fn ($sum, $group) => $sum->plus($group->subtotal), BigDecimal::zero());
            $this->assertSame('127.00', (string) $sum->toScale(2));
            $this->assertSame('127.00', Cart::subtotal());
        }
        $this->withSession([Cart::SESSION_KEY => []])->get(route('cart.index'))->assertViewHas('subtotal', '0.00');
    }
}
