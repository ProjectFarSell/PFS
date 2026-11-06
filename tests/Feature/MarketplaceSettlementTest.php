<?php

namespace Tests\Feature;

use App\Models\ReturnRequest;
use App\Models\ShipmentSettlement;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\ReturnRequestService;
use App\Services\Orders\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class MarketplaceSettlementTest extends TestCase
{
    use RefreshDatabase, ShipmentScenario;

    public function test_approved_example_balances_after_buyer_completion_and_snapshot_does_not_reprice(): void
    {
        $s = $this->shipmentScenario();
        config(['marketplace.commission_bps' => 1200, 'marketplace.rider_pay_cents' => 9000]);
        $this->completeShipment($s);
        $row = ShipmentSettlement::sole();
        $this->assertEquals(50000, $row->goods_cents);
        $this->assertEquals(4900, $row->delivery_cents);
        $this->assertEquals(4000, $row->commission_cents);
        $this->assertEquals(46000, $row->seller_cents);
        $this->assertEquals(4000, $row->rider_cents);
        $this->assertEquals(700, $row->cod_cost_cents);
        $this->assertEquals(1500, $row->operating_cost_cents);
        $this->assertEquals(2700, $row->contribution_cents);
        $this->assertEquals($row->goods_cents + $row->delivery_cents, $row->seller_cents + $row->rider_cents + $row->cod_cost_cents + $row->operating_cost_cents + $row->contribution_cents);
        $this->scenarioAction($s['buyer'], $s['part'], 'complete')->assertSessionHasErrors('fulfillment');
        $this->assertDatabaseCount('shipment_settlements', 1);
        $this->assertDatabaseCount('rider_earnings', 1);
        $this->actingAs($s['seller'])->get('/settlements')->assertOk()->assertSee('460.00')->assertDontSee('Platform contribution after reversals');
        $this->actingAs($s['admin'])->get('/settlements')->assertOk()->assertSee('27.00');
        $this->actingAs($s['buyer'])->get('/settlements')->assertForbidden();
        $this->actingAs(User::factory()->seller()->create())->get('/settlements')->assertOk()->assertDontSee($s['order']->number);
    }

    public function test_goods_reimbursement_appends_commission_reversal_without_rewriting_sale_or_rider_pay(): void
    {
        $s = $this->shipmentScenario();
        $this->completeShipment($s);
        $case = ReturnRequest::create(['order_item_id' => $s['part']->items()->sole()->id, 'quantity' => 1, 'reason' => 'damaged', 'details' => 'Damaged item returned to seller.', 'requested_amount' => 500, 'approved_amount' => 500, 'status' => 'approved']);
        app(ReturnRequestService::class)->review($case, $s['admin'], ['action' => 'refunded', 'refund_reference' => 'CASH-EXAMPLE-001', 'note' => 'Refund completed outside the platform.']);
        $this->assertDatabaseHas('settlement_adjustments', ['return_request_id' => $case->id, 'refund_cents' => 50000, 'commission_reversal_cents' => 4000, 'seller_debit_cents' => 46000]);
        $this->assertDatabaseHas('shipment_settlements', ['commission_cents' => 4000, 'contribution_cents' => 2700]);
        $this->assertDatabaseHas('rider_earnings', ['amount' => 40]);
        app(SettlementService::class)->refund($case->fresh());
        $this->assertDatabaseCount('settlement_adjustments', 1);
        $this->actingAs($s['admin'])->get('/settlements')->assertOk()->assertSee('-13.00');
    }

    public function test_historical_shipments_keep_old_rider_pay_and_are_not_backfilled(): void
    {
        $s = $this->shipmentScenario();
        $s['part']->update(['fee_policy' => null]);
        $this->completeShipment($s);
        $this->assertDatabaseHas('rider_earnings', ['amount' => 49]);
        $this->assertDatabaseCount('shipment_settlements', 0);
    }

    public function test_multiple_shops_charge_fixed_order_costs_once_and_each_rider_earns_per_shipment(): void
    {
        $s = $this->shipmentScenario();
        $shop = Shop::factory()->create();
        $second = $s['order']->fulfillments()->create(['shop_id' => $shop->id, 'shop_name' => $shop->name,
            'status' => 'delivered', 'rider_id' => $s['rider']->id, 'subtotal' => 300, 'shipping_fee' => 0,
            'cod_collected_at' => now(), 'fee_policy' => $s['part']->fresh()->fee_policy]);
        $s['order']->update(['subtotal' => 800, 'total' => 849]);
        $this->completeShipment($s);
        $this->scenarioAction($s['buyer'], $second, 'complete')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shipment_settlements', 2);
        $this->assertEquals(700, ShipmentSettlement::sum('cod_cost_cents'));
        $this->assertEquals(1500, ShipmentSettlement::sum('operating_cost_cents'));
        $this->assertEquals(8000, ShipmentSettlement::sum('rider_cents'));
        $this->assertDatabaseHas('shipment_settlements', ['fulfillment_id' => $second->id, 'contribution_cents' => -1600]);
    }

    public function test_demo_payment_and_uncollected_cod_do_not_post_cash_settlements(): void
    {
        $s = $this->shipmentScenario();
        $s['order']->update(['payment_method' => 'gateway_stub']);
        $this->completeShipment($s);
        $this->assertDatabaseCount('shipment_settlements', 0);
        $this->assertDatabaseCount('rider_earnings', 0);
    }
}
