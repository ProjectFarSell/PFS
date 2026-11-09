<?php

namespace Tests\Feature;

use App\Contracts\OrderNotificationSender;
use App\Enums\UserRole;
use App\Jobs\SendOrderNotification;
use App\Models\NotificationDelivery;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class DeliveryExceptionTest extends TestCase
{
    use RefreshDatabase, ShipmentScenario;

    public function test_failed_attempt_preserves_stock_and_records_no_cod_or_earnings(): void
    {
        $s = $this->shipmentScenario();
        $this->failShipment($s);
        $this->assertDatabaseHas('delivery_attempts', ['fulfillment_id' => $s['part']->id, 'number' => 1, 'reason' => 'unreachable']);
        $this->assertSame('delivery_failed', $s['part']->fresh()->status->value);
        $this->assertSame('delivery_issue', $s['order']->fresh()->status->value);
        $this->assertSame(9, $s['product']->fresh()->stock);
        $this->assertNull($s['part']->fresh()->cod_collected_at);
        $this->assertDatabaseCount('rider_earnings', 0);
        $this->assertDatabaseCount('shipment_settlements', 0);
        $this->actingAs($s['buyer'])->get(route('orders.show', $s['order']))->assertOk()->assertSee('Delivery attempt failed');
        $this->actingAs($s['rider']->user)->get('/fulfillments')->assertOk()->assertSee('Await admin');
        $this->get('/rider/dashboard')->assertOk()->assertSee($s['order']->number);
        $this->scenarioAction($s['buyer'], $s['part'], 'complete')->assertSessionHasErrors('fulfillment');
    }

    public function test_only_assigned_rider_can_report_and_required_details_are_validated(): void
    {
        $s = $this->shipmentScenario();
        $payload = ['failure_reason' => 'refused', 'contact_method' => 'doorstep', 'exception_note' => 'Recipient refused the sealed goods.'];
        foreach ([$s['buyer'], $s['seller'], $s['admin'], User::factory()->create(['role' => UserRole::Rider])] as $actor) {
            $this->scenarioAction($actor, $s['part'], 'fail', $payload)->assertForbidden();
        }
        $this->scenarioAction($s['rider']->user, $s['part'], 'fail')->assertSessionHasErrors(['failure_reason', 'contact_method', 'exception_note']);
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_admin_can_schedule_retry_and_delivery_is_blocked_until_due(): void
    {
        $s = $this->shipmentScenario();
        $this->failShipment($s);
        $payload = ['retry_at' => now()->addHour()->toDateTimeString(), 'exception_note' => 'Buyer confirmed availability by phone tomorrow.'];
        $this->scenarioAction($s['seller'], $s['part'], 'retry', $payload)->assertForbidden();
        $this->scenarioAction($s['admin'], $s['part'], 'retry', $payload)->assertSessionHasNoErrors();
        $this->scenarioAction($s['rider']->user, $s['part'], 'deliver', ['delivery_note' => 'Too soon for retry.', 'delivery_recipient_name' => 'Buyer', 'delivery_pin' => $s['part']->fresh()->delivery_pin, 'cod_collected' => 1])->assertSessionHasErrors('fulfillment');
        $this->travel(61)->minutes();
        $this->completeShipment($s);
        $this->assertSame('completed', $s['order']->fresh()->status->value);
        $this->assertDatabaseCount('delivery_attempts', 1);
        $this->assertDatabaseHas('rider_earnings', ['fulfillment_id' => $s['part']->id, 'amount' => 40]);
    }

    public function test_return_requires_seller_receipt_then_restores_stock_once_and_waives_cod(): void
    {
        $s = $this->shipmentScenario(true);
        $this->failShipment($s, 'buyer_wrong_variant');
        $this->scenarioAction($s['admin'], $s['part'], 'return_to_seller', ['exception_note' => 'Return all goods to the pickup shop.'])->assertSessionHasNoErrors();
        $this->assertSame(9, $s['option']->fresh()->stock);
        $payload = ['return_received' => 1, 'return_disposition' => 'restock', 'exception_note' => 'All sealed goods inspected and received.'];
        $this->scenarioAction($s['admin'], $s['part'], 'receive_return', $payload)->assertForbidden();
        $this->scenarioAction($s['seller'], $s['part'], 'receive_return', $payload)->assertSessionHasNoErrors();
        $this->assertSame(10, $s['product']->fresh()->stock);
        $this->assertSame(10, $s['option']->fresh()->stock);
        $this->assertSame('returned', $s['order']->fresh()->status->value);
        $this->assertEquals(0, $s['part']->fresh()->amount());
        $this->assertCount(0, $s['order']->fresh()->billableItems());
        $this->scenarioAction($s['seller'], $s['part'], 'receive_return', $payload)->assertSessionHasErrors('fulfillment');
        $this->assertSame(10, $s['option']->fresh()->stock);
        $this->assertDatabaseCount('rider_earnings', 0);
        $this->actingAs($s['buyer'])->get(route('orders.print', ['order' => $s['order'], 'document' => 'invoice']))->assertOk()->assertSee('0.00');
    }

    public function test_quarantined_return_does_not_put_damaged_goods_back_on_sale(): void
    {
        $s = $this->shipmentScenario();
        $this->returnShipment($s, 'quarantine');
        $this->assertSame(9, $s['product']->fresh()->stock);
        $this->assertSame('quarantine', $s['part']->fresh()->return_disposition);
    }

    public function test_retry_limit_blocks_fourth_attempt_and_return_stays_available(): void
    {
        $s = $this->shipmentScenario();
        for ($i = 1; $i <= 3; $i++) {
            $this->failShipment($s);
            $response = $this->scenarioAction($s['admin'], $s['part'], 'retry', ['retry_at' => now()->addMinute()->toDateTimeString(), 'exception_note' => 'Buyer asked for another delivery attempt.']);
            if ($i < 3) {
                $response->assertSessionHasNoErrors();
                $this->travel(2)->minutes();
            } else {
                $response->assertSessionHasErrors('fulfillment');
            }
        }
        $this->assertDatabaseCount('delivery_attempts', 3);
        $this->scenarioAction($s['admin'], $s['part'], 'return_to_seller', ['exception_note' => 'Retry limit reached; return to pickup shop.'])->assertSessionHasNoErrors();
    }

    public function test_returned_sibling_does_not_cancel_successfully_completed_shipment_or_invoice(): void
    {
        $s = $this->shipmentScenario();
        $shop = Shop::factory()->create();
        $other = $s['order']->fulfillments()->create(['shop_id' => $shop->id, 'shop_name' => $shop->name,
            'status' => 'completed', 'subtotal' => 100, 'shipping_fee' => 10]);
        $s['order']->items()->create(['fulfillment_id' => $other->id, 'name' => 'Retained purchase', 'product_sku' => 'KEEP-BKS1', 'qty' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $this->returnShipment($s);
        $order = $s['order']->fresh();
        $this->assertSame('completed', $order->status->value);
        $this->assertCount(1, $order->billableItems());
        $this->assertEquals(10, $order->billableShipping());
        $this->actingAs($s['buyer'])->get(route('orders.print', ['order' => $order, 'document' => 'packing-slip']))->assertOk()->assertSee('Retained purchase')->assertDontSee('Scenario Shirt');
    }

    public function test_rider_instruction_emails_skip_old_retry_and_deliver_current_return_instruction(): void
    {
        $s = $this->shipmentScenario();
        Queue::fake();
        config(['notifications.enabled' => true]);
        $this->failShipment($s);
        $this->scenarioAction($s['admin'], $s['part'], 'retry', ['retry_at' => now()->addMinute()->toDateTimeString(), 'exception_note' => 'Buyer arranged a second delivery attempt.'])->assertSessionHasNoErrors();
        $old = NotificationDelivery::where('type', 'rider_exception_update')->sole();
        $this->travel(2)->minutes();
        $this->failShipment($s);
        $sender = \Mockery::mock(OrderNotificationSender::class);
        $sender->shouldNotReceive('send');
        (new SendOrderNotification($old->id))->handle($sender);
        $this->assertSame('skipped', $old->fresh()->status);
        $this->scenarioAction($s['admin'], $s['part'], 'return_to_seller', ['exception_note' => 'Return to pickup shop; buyer declined retry.'])->assertSessionHasNoErrors();
        $latest = NotificationDelivery::where('type', 'rider_exception_update')->latest('id')->firstOrFail();
        $sender = \Mockery::mock(OrderNotificationSender::class);
        $sender->shouldReceive('send')->once();
        (new SendOrderNotification($latest->id))->handle($sender);
        $this->assertSame('sent', $latest->fresh()->status);
    }
}
