<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_log_in_and_non_admin_roles_are_forbidden(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->post(route('guest.start'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        foreach ([UserRole::Buyer, UserRole::Seller, UserRole::Rider] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.dashboard'))->assertForbidden();
        }
    }

    public function test_admin_sees_real_counts_marketplace_orders_and_low_stock_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->order($buyer);
        $riderDetails = ['vehicle_type' => 'motorcycle', 'license_no' => 'DEMO-123', 'city' => 'Quezon City'];
        $buyer->riderProfile()->create(['status' => RiderStatus::Pending, ...$riderDetails]);
        $admin->riderProfile()->create(['status' => RiderStatus::Approved, ...$riderDetails]);
        $low = Product::factory()->create(['name' => 'Low stock test product', 'stock' => 2]);
        $out = Product::factory()->create(['name' => 'Out of stock test product', 'stock' => 0]);
        $normal = Product::factory()->create(['name' => 'Well stocked test product', 'stock' => 6]);
        $inactive = Product::factory()->create(['name' => 'Inactive test product', 'stock' => 0, 'is_active' => false]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Marketplace overview')->assertSee($buyer->name)->assertSee($order->number)
            ->assertSee(route('orders.show', $order), false)->assertSee('Awaiting payment')
            ->assertSee($low->name)->assertSee($out->name)->assertSee('Out of stock')
            ->assertDontSee($normal->name)->assertDontSee($inactive->name)
            ->assertViewHas('lowStockCount', 2)
            ->assertViewHas('stats', fn ($stats) => $stats['products'] === 3 && $stats['orders'] === 1
                && $stats['pendingRiders'] === 1 && $stats['buyers'] === User::where('role', UserRole::Buyer)->count());

        $this->get(route('orders.show', $order))->assertOk();
    }

    public function test_orders_filter_and_pagination_preserve_status_and_newest_first_ordering(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create();
        $cancelled = $this->order($buyer, ['status' => OrderStatus::Cancelled]);
        $oldest = $this->order($buyer, ['created_at' => now()->subDay()]);
        $recent = collect(range(1, 10))->map(fn () => $this->order($buyer));

        $this->actingAs($admin)->get(route('admin.dashboard', ['status' => 'pending_payment']))
            ->assertOk()->assertDontSee($cancelled->number)->assertDontSee($oldest->number)
            ->assertSeeInOrder($recent->reverse()->pluck('number')->all())
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 11 && $orders->count() === 10
                && str_contains($orders->nextPageUrl(), 'status=pending_payment'));
        $this->get(route('admin.dashboard', ['status' => 'pending_payment', 'page' => 2]))
            ->assertOk()->assertSee($oldest->number)->assertDontSee($recent->last()->number);
        $this->getJson(route('admin.dashboard', ['status' => 'not-a-status']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_empty_dashboard_and_empty_filtered_results_are_supported(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('admin.dashboard'))->assertOk()
            ->assertSee('No orders have been placed yet.')
            ->assertSee('No active products are low on stock.');
        $this->get(route('admin.dashboard', ['status' => 'delivered']))->assertOk()
            ->assertSee('No orders match this status.');
    }

    public function test_admin_link_is_only_shown_to_administrators(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Buyer]))
            ->get(route('account.profile'))->assertOk()->assertDontSee(route('admin.dashboard'), false);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('account.profile'))->assertOk()->assertSee('Admin Dashboard')
            ->assertSee(route('admin.dashboard'), false);
    }

    public function test_alerts_are_bounded_and_dashboard_does_not_change_order_or_stock(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order($admin);
        $products = collect(range(0, 5))->map(fn ($stock) => Product::factory()->create(['stock' => $stock]));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('lowStockCount', 6)
            ->assertViewHas('lowStockProducts', fn ($rows) => $rows->count() === 5
                && $rows->pluck('stock')->all() === [0, 1, 2, 3, 4]);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame([0, 1, 2, 3, 4, 5], $products->map(fn ($product) => (int) $product->fresh()->stock)->all());
    }

    private function order(User $buyer, array $attributes = []): Order
    {
        return $buyer->orders()->create([
            'number' => 'ADMIN-'.fake()->unique()->numerify('############'),
            'status' => OrderStatus::PendingPayment,
            'payment_method' => PaymentMethod::Cod,
            'ship_to' => 'Demo address',
            'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149,
            ...$attributes,
        ]);
    }
}
