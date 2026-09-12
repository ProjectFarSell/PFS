<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartStockPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_rejects_quantity_above_available_stock(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->from(route('products.show', $product))
            ->post('/cart', ['product_id' => $product->id, 'qty' => 2])
            ->assertSessionHasErrors('qty');
    }
}
