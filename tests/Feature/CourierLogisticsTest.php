<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Models\Address;
use App\Models\CourierBooking;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\ShipmentSettlement;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\DeliveryRequestService;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class CourierLogisticsTest extends TestCase
{
    use RefreshDatabase, ShipmentScenario;

    private function external(string $method = 'lalamove'): array
    {
        config(['notifications.enabled' => false]);
        Http::preventStrayRequests();
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'city' => 'Quezon City']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 500, 'stock' => 10]);
        $address = Address::factory()->create(['user_id' => $buyer->id, 'city' => 'Quezon City']);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [Cart::lineKey($product->id) => 1]])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $part = $order->fulfillments()->sole();
        $this->scenarioAction($seller, $part, 'accept')->assertSessionHasNoErrors();
        $this->scenarioAction($seller, $part, 'ready', ['delivery_method' => $method,
            'pickup_address' => '123 Shop Street', 'pickup_contact' => 'Seller 09171234567'])->assertSessionHasNoErrors();

        return compact('buyer', 'seller', 'admin', 'shop', 'product', 'order', 'part');
    }

    private function logistics(array $s, string $action, array $data = [], ?User $actor = null)
    {
        return $this->actingAs($actor ?? $s['admin'])->from('/admin/logistics')
            ->post(route('admin.logistics.update', $s['part']), ['action' => $action, ...$data]);
    }

    private function manual(array $s, array $overrides = [])
    {
        return $this->logistics($s, 'manual', [...[
            'reference' => 'LALA-EXAMPLE-001', 'courier_cost' => '80.00', 'verified' => 1, 'cod_supported' => 1,
            'note' => 'Verified courier booking and COD acceptance in provider portal.',
        ], ...$overrides]);
    }

    private function apiConfig(): void
    {
        config(['logistics.lalamove.enabled' => true, 'logistics.lalamove.environment' => 'sandbox',
            'logistics.lalamove.key' => 'pk_test_fixture', 'logistics.lalamove.secret' => 'sk_test_fixture']);
    }

    private function quotation(array $s)
    {
        return $this->logistics($s, 'quote', ['service_type' => 'MOTORCYCLE',
            'pickup_address' => '123 Shop Street', 'dropoff_address' => '456 Buyer Street',
            'pickup_lat' => '14.6000', 'pickup_lng' => '121.0000', 'dropoff_lat' => '14.7000', 'dropoff_lng' => '121.1000',
            'sender_name' => 'Fixture Seller', 'sender_phone' => '+639171234567',
            'recipient_name' => 'Fixture Buyer', 'recipient_phone' => '+639181234567']);
    }

    private function quoteResponse(): array
    {
        return ['data' => ['quotationId' => 'quote-fixture', 'stops' => [['stopId' => 'pickup-1'], ['stopId' => 'dropoff-2']],
            'priceBreakdown' => ['total' => '80', 'currency' => 'PHP']]];
    }

    public function test_manual_external_cod_flow_preserves_buyer_charge_and_records_actual_cost_without_rider_credit(): void
    {
        $s = $this->external();
        $this->manual($s, ['tracking_url' => 'https://share.lalamove.com/fixture-tracking'])->assertSessionHasNoErrors();
        $this->assertEquals('assigned', $s['part']->fresh()->status->value);
        $this->assertEquals(549, $s['order']->fresh()->total);
        $this->assertFalse($s['order']->fresh()->canBuyerCancel());
        $this->scenarioAction($s['admin'], $s['part'], 'pickup', ['courier_verified' => 1])->assertSessionHasNoErrors();
        $this->actingAs($s['buyer'])->get(route('orders.show', $s['order']))->assertOk()->assertSee('LALA-EXAMPLE-001')
            ->assertSee('Track with courier')->assertDontSee('Delivery PIN:');
        $this->scenarioAction($s['admin'], $s['part'], 'deliver', ['courier_verified' => 1,
            'delivery_recipient_name' => 'Buyer', 'delivery_note' => 'Courier POD reference verified.', 'cod_collected' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shipment_settlements', 0);
        $this->scenarioAction($s['buyer'], $s['part'], 'complete')->assertSessionHasNoErrors();
        $row = ShipmentSettlement::sole();
        $this->assertEquals(8000, $row->courier_cost_cents);
        $this->assertEquals(0, $row->rider_cents);
        $this->assertEquals(46000, $row->seller_cents);
        $this->assertEquals(-1300, $row->contribution_cents);
        $this->assertEquals(54900, $row->seller_cents + $row->courier_cost_cents + $row->rider_cents + $row->cod_cost_cents + $row->operating_cost_cents + $row->contribution_cents);
        $this->assertDatabaseCount('rider_earnings', 0);
        $this->actingAs($s['admin'])->get('/settlements')->assertOk()->assertSee('External courier cost')->assertSee('80.00')->assertSee('-13.00');
        $this->actingAs($s['admin'])->get('/admin/logistics')->assertOk()->assertSee('LALA-EXAMPLE-001')->assertDontSee('sk_test_fixture');
        $this->scenarioAction($s['buyer'], $s['part'], 'complete')->assertSessionHasErrors();
        $this->assertDatabaseCount('shipment_settlements', 1);
    }

    public function test_external_shipments_are_excluded_from_rider_dispatch_and_assignment(): void
    {
        $s = $this->external();
        $rider = RiderProfile::create(['user_id' => User::factory()->create(['role' => 'rider'])->id,
            'status' => 'approved', 'city' => 'Quezon City', 'vehicle_type' => 'motorcycle', 'license_no' => 'FIXTURE', 'is_available' => true]);
        app(DeliveryRequestService::class)->dispatchPending();
        app(DeliveryRequestService::class)->notifyAvailableRider($rider);
        $this->assertDatabaseCount('rider_delivery_requests', 0);
        $this->assertSame(0, app(DeliveryRequestService::class)->requests($rider)->count());
        $this->scenarioAction($rider->user, $s['part'], 'claim')->assertForbidden();
        $this->scenarioAction($s['admin'], $s['part'], 'assign', ['rider_id' => $rider->id])->assertForbidden();
        $this->scenarioAction($s['seller'], $s['part'], 'request_rider', ['pickup_address' => '123 Shop Street', 'pickup_contact' => 'Seller phone'])->assertSessionHasErrors();
        $this->actingAs($s['admin'])->get('/fulfillments')->assertOk()->assertDontSee('name="rider_id"', false)->assertSee('Manage logistics / courier booking')->assertDontSee('J&T');
    }

    public function test_admin_can_switch_only_unbooked_unassigned_shipments_and_seller_controls_are_visible(): void
    {
        $s = $this->external();
        $this->actingAs($s['seller'])->get('/fulfillments')->assertOk()->assertSee('Waiting for admin to arrange');
        $this->logistics($s, 'select', ['delivery_method' => 'jt_express'])->assertSessionHasErrors('delivery_method');
        $this->logistics($s, 'select', ['delivery_method' => 'internal'])->assertSessionHasNoErrors();
        $this->assertEquals(DeliveryMethod::Internal, $s['part']->fresh()->delivery_method);
        $this->logistics($s, 'select', ['delivery_method' => 'lalamove'])->assertSessionHasNoErrors();
        $this->assertEquals(DeliveryMethod::Lalamove, $s['part']->fresh()->delivery_method);
        $this->manual($s)->assertSessionHasNoErrors();
        $this->logistics($s, 'select', ['delivery_method' => 'internal'])->assertSessionHasErrors('logistics');
    }

    public function test_admin_only_booking_and_verified_cod_actions_are_enforced(): void
    {
        $s = $this->external();
        $this->actingAs($s['buyer'])->get('/admin/logistics')->assertForbidden();
        $this->actingAs($s['seller'])->get('/admin/logistics')->assertForbidden();
        $this->logistics($s, 'manual', [], $s['seller'])->assertForbidden();
        $this->manual($s, ['verified' => 0])->assertSessionHasErrors('verified');
        $this->manual($s, ['cod_supported' => 0])->assertSessionHasErrors('logistics');
        $this->assertDatabaseCount('courier_bookings', 0);
        $this->manual($s)->assertSessionHasNoErrors();
        $this->scenarioAction($s['seller'], $s['part'], 'pickup', ['courier_verified' => 1])->assertForbidden();
        $this->scenarioAction($s['admin'], $s['part'], 'pickup')->assertSessionHasErrors('fulfillment');
        $this->scenarioAction($s['admin'], $s['part'], 'pickup', ['courier_verified' => 1])->assertSessionHasNoErrors();
        $this->scenarioAction($s['admin'], $s['part'], 'deliver', ['courier_verified' => 1,
            'delivery_recipient_name' => 'Buyer', 'delivery_note' => 'Verified handover details.'])->assertSessionHasErrors('cod_collected');
        $this->assertNull($s['part']->fresh()->cod_collected_at);
    }

    public function test_tracking_links_costs_and_duplicate_references_are_validated(): void
    {
        $s = $this->external();
        foreach (['https://evil.example/track', 'https://share.lalamove.com.evil.example/', 'http://share.lalamove.com/', 'https://user@share.lalamove.com/'] as $url) {
            $this->manual($s, ['tracking_url' => $url])->assertSessionHasErrors();
        }
        $this->manual($s, ['courier_cost' => '-1'])->assertSessionHasErrors();
        $this->manual($s, ['courier_cost' => '80.001'])->assertSessionHasErrors();
        $this->manual($s)->assertSessionHasNoErrors();
        $second = $this->external();
        $this->manual($second)->assertSessionHasErrors('logistics');
        $this->assertDatabaseCount('courier_bookings', 1);
    }

    public function test_external_failed_delivery_retry_and_physical_return_restore_stock_once(): void
    {
        $s = $this->external();
        $this->manual($s)->assertSessionHasNoErrors();
        $this->scenarioAction($s['admin'], $s['part'], 'pickup', ['courier_verified' => 1])->assertSessionHasNoErrors();
        $fail = ['failure_reason' => 'unreachable', 'contact_method' => 'phone', 'exception_note' => 'Courier called buyer twice; no answer.', 'courier_verified' => 1];
        $this->scenarioAction($s['admin'], $s['part'], 'fail', $fail)->assertSessionHasNoErrors();
        $this->assertEquals(9, $s['product']->fresh()->stock);
        $this->actingAs($s['admin'])->get('/fulfillments')->assertOk()->assertSee('Record agreed courier retry');
        $this->scenarioAction($s['admin'], $s['part'], 'retry', ['retry_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'exception_note' => 'Confirmed new attempt with buyer and courier.', 'courier_verified' => 1])->assertSessionHasNoErrors();
        $this->scenarioAction($s['admin'], $s['part'], 'fail', $fail)->assertSessionHasErrors();
        $this->travel(2)->hours();
        $this->scenarioAction($s['admin'], $s['part'], 'fail', $fail)->assertSessionHasNoErrors();
        $this->scenarioAction($s['admin'], $s['part'], 'return_to_seller', ['courier_verified' => 1, 'exception_note' => 'Courier confirmed return to original shop.'])->assertSessionHasNoErrors();
        $receipt = ['return_received' => 1, 'return_disposition' => 'restock', 'exception_note' => 'All returned goods received and inspected.'];
        $this->scenarioAction($s['seller'], $s['part'], 'receive_return', $receipt)->assertSessionHasNoErrors();
        $this->scenarioAction($s['seller'], $s['part'], 'receive_return', $receipt)->assertSessionHasErrors();
        $this->assertEquals(10, $s['product']->fresh()->stock);
        $this->assertDatabaseCount('shipment_settlements', 0);
        $this->assertDatabaseCount('rider_earnings', 0);
    }

    public function test_verified_courier_cancellation_allows_buyer_to_cancel_without_duplicate_stock_release(): void
    {
        $s = $this->external();
        $this->manual($s)->assertSessionHasNoErrors();
        $this->logistics($s, 'cancel_record', ['note' => 'Carrier cancelled before pickup.'])->assertSessionHasErrors('verified_cancelled');
        $this->logistics($s, 'cancel_record', ['verified_cancelled' => 1, 'note' => 'Carrier portal confirms cancellation before pickup.'])->assertSessionHasNoErrors();
        $this->assertTrue($s['order']->fresh()->canBuyerCancel());
        $this->assertEquals(9, $s['product']->fresh()->stock);
        $this->actingAs($s['buyer'])->post(route('orders.cancel', $s['order']), ['reason' => 'Courier booking cancelled before pickup.'])->assertSessionHasNoErrors();
        $this->assertEquals(10, $s['product']->fresh()->stock);
        $this->assertDatabaseHas('courier_bookings', ['status' => 'cancelled', 'reference' => 'LALA-EXAMPLE-001']);
    }

    public function test_missing_credentials_leave_manual_mode_usable_and_do_not_make_http_calls(): void
    {
        $s = $this->external('lalamove');
        config(['logistics.lalamove.enabled' => false]);
        Http::fake();
        $this->quotation($s)->assertSessionHasErrors('logistics');
        Http::assertNothingSent();
        $this->actingAs($s['admin'])->get('/admin/logistics')->assertOk()->assertSee('manual booking available')->assertSee('disabled', false);
        $this->manual($s)->assertSessionHasNoErrors();
    }

    public function test_lalamove_quotes_are_signed_and_sensitive_details_are_encrypted_and_hidden(): void
    {
        $s = $this->external('lalamove');
        $this->apiConfig();
        Http::fake(['rest.sandbox.lalamove.com/v3/quotations' => Http::response($this->quoteResponse())]);
        $this->quotation($s)->assertSessionHasNoErrors();
        Http::assertSent(function (ClientRequest $request) {
            $authorization = $request->header('Authorization')[0];
            [$key, $timestamp, $signature] = explode(':', substr($authorization, 5));
            $this->assertEquals('pk_test_fixture', $key);
            $this->assertEquals(hash_hmac('sha256', $timestamp."\r\nPOST\r\n/v3/quotations\r\n\r\n".$request->body(), 'sk_test_fixture'), $signature);
            $this->assertSame('PH', $request->header('Market')[0]);
            $this->assertEquals('en_PH', $request['data']['language']);

            return $request->url() === 'https://rest.sandbox.lalamove.com/v3/quotations';
        });
        $booking = CourierBooking::sole();
        $this->assertEquals('quote-fixture', $booking->details['quotationId']);
        $this->assertStringNotContainsString('Fixture Buyer', DB::table('courier_bookings')->value('details'));
        $this->assertArrayNotHasKey('details', $booking->toArray());
        $this->actingAs($s['admin'])->get('/admin/logistics')->assertOk()->assertSee('Create sandbox booking')->assertDontSee('sk_test_fixture');
    }

    public function test_sandbox_booking_is_single_submit_and_provider_completion_does_not_confirm_delivery_or_cash(): void
    {
        $s = $this->external('lalamove');
        $this->apiConfig();
        Http::fake([
            'rest.sandbox.lalamove.com/v3/quotations' => Http::response($this->quoteResponse()),
            'rest.sandbox.lalamove.com/v3/orders' => Http::response(['data' => ['orderId' => '1234567890123456789', 'status' => 'ASSIGNING_DRIVER', 'shareLink' => 'https://evil.example/']]),
            'rest.sandbox.lalamove.com/v3/orders/*' => Http::response(['data' => ['orderId' => '1234567890123456789', 'status' => 'COMPLETED']]),
        ]);
        $this->quotation($s)->assertSessionHasNoErrors();
        $this->logistics($s, 'book', ['confirmed_booking' => 1])->assertSessionHasNoErrors();
        $this->logistics($s, 'book', ['confirmed_booking' => 1])->assertSessionHasErrors();
        Http::assertSentCount(2);
        $this->assertNull(CourierBooking::sole()->tracking_url);
        $this->logistics($s, 'sync')->assertSessionHasNoErrors();
        $this->assertEquals('assigned', $s['part']->fresh()->status->value);
        $this->assertEquals('COMPLETED', CourierBooking::sole()->provider_status);
        $this->scenarioAction($s['admin'], $s['part'], 'pickup', ['courier_verified' => 1])->assertSessionHasNoErrors();
        $this->scenarioAction($s['admin'], $s['part'], 'deliver', ['courier_verified' => 1, 'cod_collected' => 1,
            'delivery_recipient_name' => 'Test recipient', 'delivery_note' => 'Simulated sandbox proof only.'])->assertSessionHasNoErrors();
        $this->scenarioAction($s['buyer'], $s['part'], 'complete')->assertSessionHasNoErrors();
        $this->assertNull($s['part']->fresh()->cod_collected_at);
        $this->assertDatabaseCount('shipment_settlements', 0);
        $this->assertDatabaseCount('rider_earnings', 0);
    }

    public function test_uncertain_booking_blocks_resubmit_and_cancellation_until_reconciled(): void
    {
        $s = $this->external('lalamove');
        $this->apiConfig();
        Http::fake(['rest.sandbox.lalamove.com/v3/quotations' => Http::response($this->quoteResponse()),
            'rest.sandbox.lalamove.com/v3/orders' => Http::failedConnection()]);
        $this->quotation($s)->assertSessionHasNoErrors();
        $this->logistics($s, 'book', ['confirmed_booking' => 1])->assertSessionHasErrors('logistics');
        $this->assertEquals('unknown', CourierBooking::sole()->status);
        $this->assertFalse($s['order']->fresh()->canBuyerCancel());
        $this->logistics($s, 'book', ['confirmed_booking' => 1])->assertSessionHasErrors();
        $this->quotation($s)->assertSessionHasErrors();
        Http::assertSentCount(2);
        $this->manual($s, ['reference' => 'RECONCILED-SANDBOX', 'cod_supported' => 0])->assertSessionHasNoErrors();
        $this->assertTrue(CourierBooking::sole()->isSandbox());
        $this->assertEquals('api', CourierBooking::sole()->mode);
    }

    public function test_expired_or_environment_changed_quotes_and_production_bookings_are_blocked_before_http(): void
    {
        $s = $this->external('lalamove');
        $this->apiConfig();
        Http::fake(['rest.sandbox.lalamove.com/v3/quotations' => Http::response($this->quoteResponse())]);
        $this->quotation($s)->assertSessionHasNoErrors();
        $this->travel(6)->minutes();
        $this->logistics($s, 'book', ['confirmed_booking' => 1])->assertSessionHasErrors();
        $this->travelBack();
        config(['logistics.lalamove.environment' => 'production', 'logistics.lalamove.allow_live_bookings' => true]);
        $this->logistics($s, 'book', ['confirmed_booking' => 1])->assertSessionHasErrors();
        CourierBooking::sole()->update(['api_environment' => 'production']);
        $this->logistics($s, 'book', ['confirmed_booking' => 1])->assertSessionHasErrors();
        Http::assertSentCount(1);
    }

    public function test_recent_in_flight_submission_cannot_be_cancelled_or_reconciled_but_stale_one_can(): void
    {
        $s = $this->external('lalamove');
        CourierBooking::create(['fulfillment_id' => $s['part']->id, 'provider' => 'lalamove', 'mode' => 'api', 'api_environment' => 'sandbox', 'status' => 'submitting']);
        $this->manual($s)->assertSessionHasErrors();
        $this->logistics($s, 'cancel_record', ['verified_cancelled' => 1, 'note' => 'Provider portal confirms non-creation.'])->assertSessionHasErrors();
        $this->travel(3)->minutes();
        $this->manual($s, ['cod_supported' => 0])->assertSessionHasNoErrors();
        $this->assertTrue(CourierBooking::sole()->isSandbox());
    }

    public function test_external_admin_writes_still_require_recent_confirmation_when_security_is_enabled(): void
    {
        $s = $this->external();
        config(['security.sensitive_confirmation_required' => true]);
        $this->manual($s)->assertRedirect(route('security.index'));
        $this->assertDatabaseCount('courier_bookings', 0);
    }
}
