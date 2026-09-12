<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderStateMachine;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_moves_only_through_allowed_states(): void
    {
        $order = $this->order();
        $machine = app(OrderStateMachine::class);

        $order = $machine->transition($order, OrderStatus::Paid);
        $order = $machine->transition($order, OrderStatus::Packed);

        $this->assertSame(OrderStatus::Packed, $order->status);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        app(OrderStateMachine::class)->transition($this->order(), OrderStatus::Delivered);
    }

    public function test_cancelling_reserved_order_releases_stock_once(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = $this->order();
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => 2,
            'unit_price' => $product->price,
            'line_total' => (float) $product->price * 2,
        ]);

        $cancelled = app(OrderStateMachine::class)->transition($order, OrderStatus::Cancelled);

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertNotNull($cancelled->stock_released_at);
    }

    private function order(): Order
    {
        return Order::query()->create([
            'user_id' => User::factory()->create()->id,
            'number' => 'FS'.fake()->unique()->numerify('########'),
            'status' => OrderStatus::PendingPayment,
            'payment_method' => PaymentMethod::Cod,
            'ship_to' => 'Test address',
            'subtotal' => 100,
            'shipping_fee' => 49,
            'total' => 149,
            'stock_reserved_at' => now(),
        ]);
    }
}
