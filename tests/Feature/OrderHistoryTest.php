<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_sign_in_to_view_order_history_and_details(): void
    {
        $order = $this->order(User::factory()->create());
        $this->get(route('orders.index'))->assertRedirect(route('login'));
        $this->get(route('orders.show', $order))->assertRedirect(route('login'));
    }

    public function test_history_only_contains_owned_orders_and_links_to_their_details(): void
    {
        $buyer = User::factory()->create();
        $owned = $this->order($buyer);
        $other = $this->order(User::factory()->create());
        $owned->items()->create([
            'name' => 'Test purchase', 'qty' => 2, 'unit_price' => 50, 'line_total' => 100,
        ]);

        $this->actingAs($buyer)->get(route('orders.index', ['user_id' => $other->user_id]))
            ->assertOk()->assertSee($owned->number)->assertDontSee($other->number)
            ->assertSee('2 items')->assertSee('Awaiting payment')
            ->assertSee('149.00')->assertSee(route('orders.show', $owned), false);
        $this->get(route('orders.show', $owned))->assertOk()->assertSee('Test purchase')
            ->assertSee(route('orders.index'), false);
        $this->get(route('orders.show', $other))->assertForbidden();
    }

    public function test_history_is_paginated_newest_first_with_stable_ordering(): void
    {
        $buyer = User::factory()->create();
        $oldest = $this->order($buyer, ['created_at' => now()->subDay()]);
        $recent = collect(range(1, 10))->map(fn () => $this->order($buyer));

        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertOk()->assertDontSee($oldest->number)
            ->assertSeeInOrder($recent->reverse()->pluck('number')->all())
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 11 && $orders->count() === 10);
        $this->get(route('orders.index', ['page' => 2]))
            ->assertOk()->assertSee($oldest->number)->assertDontSee($recent->last()->number);
    }

    public function test_empty_history_offers_a_way_back_to_products(): void
    {
        $this->actingAs(User::factory()->create())->get(route('orders.index'))
            ->assertOk()->assertSee('placed any orders yet.')
            ->assertSee('Browse products')->assertSee(route('home'), false);
    }

    public function test_admin_my_orders_still_only_lists_their_own_purchases(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owned = $this->order($admin, ['status' => OrderStatus::Cancelled]);
        $other = $this->order(User::factory()->create());
        $this->actingAs($admin)->get(route('orders.index'))
            ->assertOk()->assertSee($owned->number)->assertSee('Cancelled')->assertDontSee($other->number);
        // Preserve existing administrator access to individual order details.
        $this->get(route('orders.show', $other))->assertOk();
    }

    private function order(User $buyer, array $attributes = []): Order
    {
        return $buyer->orders()->create([
            'number' => 'FS'.fake()->unique()->numerify('############'),
            'status' => OrderStatus::PendingPayment,
            'payment_method' => PaymentMethod::Cod,
            'ship_to' => 'Test delivery address',
            'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149,
            ...$attributes,
        ]);
    }
}
