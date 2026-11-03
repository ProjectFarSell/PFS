<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BuyerNotification;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_survives_disabled_email_and_repeated_events(): void
    {
        config(['notifications.enabled' => false]);
        $buyer = User::factory()->create();
        $order = Order::query()->create(['user_id' => $buyer->id, 'number' => 'FS-INBOX', 'status' => 'pending_payment', 'payment_method' => 'cod', 'ship_to' => 'Test address', 'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149]);
        $service = app(OrderNotificationService::class);
        $service->orderPlaced($order);
        $service->orderPlaced($order);
        $notification = BuyerNotification::query()->sole();
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->actingAs($buyer)->get(route('account.notifications.unread'))->assertJson(['unread' => 1]);
        $this->get(route('account.notifications.index'))->assertOk()->assertSee($order->number)->assertSee('Unread');
        $this->post(route('account.notifications.open', $notification))->assertRedirect(route('orders.show', $order));
        $this->assertNotNull($notification->fresh()->read_at);
        $this->get(route('account.notifications.unread'))->assertJson(['unread' => 0]);
    }

    public function test_other_buyers_cannot_read_or_mark_notifications(): void
    {
        config(['notifications.enabled' => false]);
        $buyer = User::factory()->create();
        $order = Order::query()->create(['user_id' => $buyer->id, 'number' => 'FS-INBOX', 'status' => 'pending_payment', 'payment_method' => 'cod', 'ship_to' => 'Test address', 'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149]);
        app(OrderNotificationService::class)->orderPlaced($order);
        $notification = BuyerNotification::query()->sole();
        $this->actingAs(User::factory()->create())->get(route('account.notifications.index'))->assertDontSee($order->number);
        $this->post(route('account.notifications.open', $notification))->assertNotFound();
        $this->post(route('account.notifications.read-all'))->assertRedirect();
        $this->assertNull($notification->fresh()->read_at);
        $this->actingAs($buyer)->post(route('account.notifications.read-all'))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_inbox_requires_a_buyer_account(): void
    {
        $this->get(route('account.notifications.index'))->assertRedirect(route('login'));
        foreach ([UserRole::Seller, UserRole::Rider, UserRole::Admin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('account.notifications.index'))->assertForbidden();
        }
    }
}
