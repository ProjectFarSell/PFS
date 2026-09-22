<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_orders_are_backfilled_without_changing_stock_or_totals(): void
    {
        $migration = require database_path('migrations/2026_09_22_000001_create_fulfillments.php');
        $migration->down();
        $product = Product::factory()->create(['stock' => 3]);
        foreach ([OrderStatus::Packed, OrderStatus::Delivered, OrderStatus::Cancelled] as $status) {
            $order = Order::query()->create([
                'user_id' => User::factory()->create()->id, 'number' => 'LEGACY-'.$status->value,
                'status' => $status, 'payment_method' => PaymentMethod::Cod,
                'ship_to' => 'Old address', 'subtotal' => 200, 'shipping_fee' => 49, 'total' => 249,
                'stock_reserved_at' => now(), 'stock_released_at' => $status === OrderStatus::Cancelled ? now() : null,
            ]);
            $order->items()->create(['product_id' => $product->id, 'name' => 'Saved item', 'qty' => 2, 'unit_price' => 100, 'line_total' => 200]);
        }
        $migration->up();
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseCount('fulfillments', 3);
        $this->assertDatabaseCount('fulfillment_events', 3);
        foreach (Order::all() as $order) {
            $part = $order->fulfillments()->sole();
            $this->assertSame('249.00', $order->total);
            $this->assertSame('49.00', $part->shipping_fee);
            $this->assertSame(1, $part->items()->count());
            $this->assertSame(match ($order->status) {
                OrderStatus::Packed => 'ready', OrderStatus::Delivered => 'delivered', OrderStatus::Cancelled => 'rejected',
            }, $part->status->value);
        }
    }
}
