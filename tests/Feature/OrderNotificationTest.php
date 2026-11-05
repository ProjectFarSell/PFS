<?php

namespace Tests\Feature;

use App\Contracts\OrderNotificationSender;
use App\Enums\UserRole;
use App\Jobs\SendOrderNotification;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderEventMail;
use App\Models\Address;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Notifications\OrderNotificationService;
use App\Services\Orders\FulfillmentService;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notifications.enabled' => true, 'notifications.connection' => 'database', 'notifications.queue' => 'notifications', 'notifications.mailer' => 'array']);
    }

    private function shipment(): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'city' => 'Quezon City']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 100, 'stock' => 5]);
        $order = Order::query()->create([
            'user_id' => $buyer->id, 'number' => 'FS'.Str::upper(Str::random(10)), 'status' => 'pending_payment',
            'payment_method' => 'cod', 'guest_email' => $buyer->email, 'guest_name' => $buyer->name,
            'ship_to' => 'PRIVATE BUYER ADDRESS', 'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149,
        ]);
        $part = $order->fulfillments()->create([
            'shop_id' => $shop->id, 'shop_name' => $shop->name, 'status' => 'pending', 'subtotal' => 100, 'shipping_fee' => 49,
        ]);
        $order->items()->create([
            'fulfillment_id' => $part->id, 'product_id' => $product->id, 'name' => $product->name,
            'product_sku' => 'DEMO-BKS', 'qty' => 1, 'unit_price' => 100, 'line_total' => 100,
        ]);

        return compact('buyer', 'seller', 'shop', 'product', 'order', 'part');
    }

    private function confirmation(array $s): NotificationDelivery
    {
        return NotificationDelivery::query()->create([
            'event_key' => 'test:'.$s['order']->id, 'type' => 'order_confirmation',
            'recipient_user_id' => $s['buyer']->id, 'recipient_email' => $s['buyer']->email,
            'recipient_name' => $s['buyer']->name, 'order_id' => $s['order']->id,
            'payload' => ['title' => 'Order confirmation', 'order_number' => $s['order']->number],
        ]);
    }

    public function test_checkout_queues_buyer_and_seller_messages_without_sending_mail_during_the_request(): void
    {
        Queue::fake();
        Mail::fake();
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['stock' => 5]);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->post(route('checkout.store'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('notification_deliveries', 2);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(['order_confirmation', 'seller_new_order'], NotificationDelivery::query()->orderBy('id')->pluck('type')->all());
        Queue::assertPushed(SendOrderNotification::class, 2);
        Queue::assertPushedOn('notifications', SendOrderNotification::class);
        Mail::assertNothingSent();
    }

    public function test_order_intents_and_jobs_are_removed_when_the_domain_transaction_rolls_back(): void
    {
        Queue::fake();
        $s = $this->shipment();
        DB::beginTransaction();
        app(OrderNotificationService::class)->orderPlaced($s['order']);
        $this->assertDatabaseCount('notification_deliveries', 2);
        Queue::assertNothingPushed();
        DB::rollBack();

        $this->assertDatabaseCount('notification_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_recording_the_same_order_again_does_not_duplicate_messages_or_jobs(): void
    {
        Queue::fake();
        $s = $this->shipment();
        $notifications = app(OrderNotificationService::class);
        $notifications->orderPlaced($s['order']);
        $notifications->orderPlaced($s['order']);
        $this->assertDatabaseCount('notification_deliveries', 2);
        Queue::assertPushed(SendOrderNotification::class, 2);
    }

    public function test_queue_outage_preserves_checkout_and_scheduler_can_recover_pending_messages(): void
    {
        Bus::shouldReceive('dispatch')->twice()->andThrow(new RuntimeException('Transport password must not be stored'));
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['stock' => 5]);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->post(route('checkout.store'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, NotificationDelivery::query()->where('status', 'pending')->count());
        $this->assertStringNotContainsString('password', NotificationDelivery::query()->first()->last_error);
        Bus::fake();
        $this->artisan('notifications:dispatch')->assertSuccessful();
        Bus::assertDispatched(SendOrderNotification::class, 2);
        $this->assertSame(2, NotificationDelivery::query()->where('status', 'queued')->count());
    }

    public function test_real_database_worker_sends_both_templates_and_tracks_success(): void
    {
        Mail::fake();
        $s = $this->shipment();
        app(OrderNotificationService::class)->orderPlaced($s['order']);
        $this->assertDatabaseCount('jobs', 2);
        $this->artisan('notifications:work', ['--once' => true])->assertSuccessful();
        $this->artisan('notifications:work', ['--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(2, NotificationDelivery::query()->where('status', 'sent')->where('attempts', 1)->count());
        Mail::assertSent(OrderConfirmationMail::class, fn ($mail) => $mail->hasTo($s['buyer']->email));
        Mail::assertSent(OrderEventMail::class, fn ($mail) => $mail->hasTo($s['seller']->email));
        $confirmation = new OrderConfirmationMail($s['order']->load('items'));
        $this->assertStringContainsString('DEMO-BKS', $confirmation->render());
        $eventMail = new OrderEventMail(NotificationDelivery::query()->where('type', 'seller_new_order')->firstOrFail());
        $this->assertStringNotContainsString('PRIVATE BUYER ADDRESS', $eventMail->render());
    }

    public function test_database_worker_retries_a_transient_failure_then_sends_successfully(): void
    {
        $s = $this->shipment();
        $delivery = $this->confirmation($s);
        $sender = Mockery::mock(OrderNotificationSender::class);
        $sender->shouldReceive('send')->once()->ordered()->andThrow(new RuntimeException('Temporary SMTP outage'));
        $sender->shouldReceive('send')->once()->ordered()->andReturnNull();
        $this->instance(OrderNotificationSender::class, $sender);
        app(OrderNotificationService::class)->enqueue($delivery->id);
        $this->artisan('notifications:work', ['--once' => true])->assertSuccessful();
        $delivery->refresh();
        $this->assertSame('retrying', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->next_retry_at);
        $this->assertDatabaseCount('jobs', 1);
        $this->travel(61)->seconds();
        $this->artisan('notifications:work', ['--once' => true])->assertSuccessful();
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_exhausted_retries_are_visible_and_can_be_requeued_by_an_admin(): void
    {
        $s = $this->shipment();
        $delivery = $this->confirmation($s);
        $sender = Mockery::mock(OrderNotificationSender::class);
        $sender->shouldReceive('send')->times(4)->andThrow(new RuntimeException('SMTP unavailable'));
        $this->instance(OrderNotificationSender::class, $sender);
        app(OrderNotificationService::class)->enqueue($delivery->id);
        foreach ([0, 61, 301, 901] as $delay) {
            if ($delay) $this->travel($delay)->seconds();
            $this->artisan('notifications:work', ['--once' => true])->assertSuccessful();
        }
        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(4, $delivery->attempts);
        $this->assertNull($delivery->next_retry_at);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);

        Queue::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.notifications.index', ['status' => 'failed']))->assertOk()->assertSee($s['buyer']->email);
        $this->post(route('admin.notifications.retry', $delivery))->assertRedirect();
        $this->assertSame('queued', $delivery->fresh()->status);
        Queue::assertPushed(SendOrderNotification::class, 1);
        $this->post(route('admin.notifications.retry', $delivery))->assertStatus(422);
        Queue::assertPushed(SendOrderNotification::class, 1);
    }

    public function test_replaying_a_delivered_job_does_not_send_the_message_again(): void
    {
        Queue::fake();
        $s = $this->shipment();
        $delivery = $this->confirmation($s);
        $sender = Mockery::mock(OrderNotificationSender::class);
        $sender->shouldReceive('send')->once()->andReturnNull();
        $job = new SendOrderNotification($delivery->id);
        $job->handle($sender);
        $job->handle($sender);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    public function test_status_transitions_queue_buyer_updates_and_rider_assignment_messages(): void
    {
        Queue::fake();
        $s = $this->shipment();
        $flow = app(FulfillmentService::class);
        $flow->act($s['part'], $s['seller'], 'accept', []);
        $oldUpdate = NotificationDelivery::query()->where('type', 'shipment_update')->firstOrFail();
        $flow->act($s['part'], $s['seller'], 'ready', ['pickup_address' => 'PRIVATE SHOP ADDRESS', 'pickup_contact' => 'PRIVATE PHONE']);
        $rider = RiderProfile::query()->create([
            'user_id' => User::factory()->rider()->create()->id, 'status' => 'approved',
            'vehicle_type' => 'motorcycle', 'license_no' => 'TEST', 'city' => 'Quezon City',
        ]);
        $flow->act($s['part'], User::factory()->admin()->create(), 'assign', ['rider_id' => $rider->id]);
        $this->assertSame(3, NotificationDelivery::query()->where('type', 'shipment_update')->count());
        $this->assertDatabaseHas('notification_deliveries', ['type' => 'rider_assignment', 'recipient_user_id' => $rider->user_id]);

        $sender = Mockery::mock(OrderNotificationSender::class);
        $sender->shouldNotReceive('send');
        (new SendOrderNotification($oldUpdate->id))->handle($sender);
        $this->assertSame('skipped', $oldUpdate->fresh()->status);
        $this->assertSame(0, $oldUpdate->fresh()->attempts);
        $this->assertStringNotContainsString('PRIVATE', json_encode(NotificationDelivery::query()->where('type', 'rider_assignment')->firstOrFail()->payload));
    }

    public function test_stale_rider_assignments_and_changed_recipient_addresses_are_skipped(): void
    {
        Queue::fake();
        $s = $this->shipment();
        $delivery = $this->confirmation($s);
        $s['buyer']->update(['email' => 'changed@example.test']);
        $sender = Mockery::mock(OrderNotificationSender::class);
        $sender->shouldNotReceive('send');
        (new SendOrderNotification($delivery->id))->handle($sender);
        $this->assertSame('skipped', $delivery->fresh()->status);

        $delivery = NotificationDelivery::query()->create([
            'event_key' => 'stale-rider', 'type' => 'rider_assignment', 'recipient_user_id' => $s['seller']->id,
            'recipient_email' => $s['seller']->email, 'order_id' => $s['order']->id,
            'fulfillment_id' => $s['part']->id, 'payload' => [],
        ]);
        (new SendOrderNotification($delivery->id))->handle($sender);
        $this->assertSame('skipped', $delivery->fresh()->status);
    }

    public function test_notification_dashboard_and_retry_are_restricted_to_admins(): void
    {
        $s = $this->shipment();
        $delivery = $this->confirmation($s);
        $this->get(route('admin.notifications.index'))->assertRedirect(route('login'));
        foreach ([UserRole::Buyer, UserRole::Seller, UserRole::Rider] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.notifications.index'))->assertForbidden();
            $this->post(route('admin.notifications.retry', $delivery))->assertForbidden();
        }
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.notifications.index'))->assertOk();
    }

    public function test_disabling_notifications_prevents_new_records_and_pending_dispatch(): void
    {
        Queue::fake();
        $s = $this->shipment();
        config(['notifications.enabled' => false]);
        app(OrderNotificationService::class)->orderPlaced($s['order']);
        $this->assertDatabaseCount('notification_deliveries', 0);
        $delivery = $this->confirmation($s);
        $this->assertSame(0, app(OrderNotificationService::class)->dispatchPending());
        $this->assertSame('pending', $delivery->fresh()->status);
        Queue::assertNothingPushed();
    }
}
