<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ShipmentSettlement;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\ReturnRequestService;
use App\Services\Orders\SellerPayableBalance;
use App\Services\Orders\SellerPayableLedger;
use App\Services\Orders\SellerPayoutEligibility;
use App\Services\Orders\SettlementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class SellerPayableLedgerTest extends TestCase
{
    use RefreshDatabase, ShipmentScenario;

    public function test_settlement_and_refund_post_once_and_reposting_is_idempotent(): void
    {
        $s = $this->shipmentScenario();
        $this->completeShipment($s);
        $settlement = ShipmentSettlement::sole();
        $this->assertDatabaseHas('seller_payable_entries', ['shop_id' => $s['shop']->id, 'shipment_settlement_id' => $settlement->id, 'amount_cents' => 46000]);
        $case = ReturnRequest::create(['order_item_id' => $s['part']->items()->sole()->id, 'quantity' => 1, 'reason' => 'damaged', 'details' => 'Damaged item.', 'requested_amount' => 500, 'approved_amount' => 500, 'status' => 'approved']);
        app(ReturnRequestService::class)->review($case, $s['admin'], ['action' => 'refunded', 'refund_reference' => 'CASH-1', 'note' => 'Refund paid.']);
        $adjustment = $settlement->adjustments()->sole();
        $this->assertDatabaseHas('seller_payable_entries', ['shop_id' => $s['shop']->id, 'settlement_adjustment_id' => $adjustment->id, 'amount_cents' => -46000]);
        $ledger = app(SellerPayableLedger::class);
        $this->assertSame($ledger->postSettlement($settlement), $ledger->postSettlement($settlement));
        $this->assertSame($ledger->postAdjustment($adjustment), $ledger->postAdjustment($adjustment));
        app(SettlementService::class)->refund($case->fresh());
        $this->assertDatabaseCount('seller_payable_entries', 2);
    }

    public function test_delivery_hold_boundary_missing_event_and_open_return_statuses(): void
    {
        $this->travelTo(now()->startOfSecond());
        $s = $this->settledShipment();
        $entry = DB::table('seller_payable_entries')->where('shipment_settlement_id', $s['settlement']->id)->sole();
        $eligibility = app(SellerPayoutEligibility::class);
        $this->assertFalse($eligibility->isEligible($entry));
        $event = $s['fulfillment']->events()->create(['status' => 'delivered', 'created_at' => now()->subDays(6)]);
        $this->assertFalse($eligibility->isEligible($entry));
        $event->update(['created_at' => now()->subDays(7)]);
        $this->assertTrue($eligibility->isEligible($entry));
        $case = $this->returnCase($s);
        foreach (['pending', 'seller_approved', 'seller_declined', 'approved'] as $status) {
            $case->update(['status' => $status]);
            $this->assertFalse($eligibility->isEligible($entry), $status);
        }
        foreach (['rejected', 'refunded'] as $status) {
            $case->update(['status' => $status]);
            $this->assertTrue($eligibility->isEligible($entry), $status);
        }
    }

    public function test_settlement_and_refund_convert_decimal_snapshots_to_exact_centavos(): void
    {
        $s = $this->shipmentScenario();
        $s['part']->update(['subtotal' => '99.99', 'shipping_fee' => '49.01']);
        $this->completeShipment($s);
        $settlement = ShipmentSettlement::sole();
        $this->assertSame(9999, (int) $settlement->goods_cents);
        $this->assertSame(4901, (int) $settlement->delivery_cents);
        $this->assertSame(9199, (int) $settlement->seller_cents);
        $case = ReturnRequest::create(['order_item_id' => $s['part']->items()->sole()->id, 'quantity' => 1, 'reason' => 'damaged', 'details' => 'Damaged item.', 'requested_amount' => '5.01', 'approved_amount' => '5.01', 'status' => 'approved']);
        app(ReturnRequestService::class)->review($case, $s['admin'], ['action' => 'refunded', 'refund_reference' => 'CASH-2', 'note' => 'Refund paid.']);
        $this->assertDatabaseHas('seller_payable_entries', ['settlement_adjustment_id' => $settlement->adjustments()->sole()->id, 'amount_cents' => -461]);
    }

    public function test_refund_debit_reduces_available_while_the_credit_is_held(): void
    {
        $s = $this->settledShipment();
        $s['fulfillment']->events()->create(['status' => 'delivered', 'created_at' => now()->subDays(2)]);
        $case = $this->returnCase($s, 'refunded');
        $adjustment = $s['settlement']->adjustments()->create(['return_request_id' => $case->id, 'refund_cents' => 3000, 'commission_reversal_cents' => 0, 'seller_debit_cents' => 3000]);
        app(SellerPayableLedger::class)->postAdjustment($adjustment);
        $this->assertSame(['available_cents' => -3000, 'in_unpaid_batch_cents' => 0, 'ineligible_held_cents' => 10000], app(SellerPayableBalance::class)->forShop($s['shop']));
    }

    public function test_balance_includes_unpaid_batch_separately_and_excludes_paid_and_other_shops(): void
    {
        $s = $this->settledShipment();
        $s['fulfillment']->events()->create(['status' => 'delivered', 'created_at' => now()->subDays(7)->subSecond()]);
        $held = $this->settledShipment($s['shop'], 4500);
        $held['fulfillment']->events()->create(['status' => 'delivered', 'created_at' => now()->subDays(1)]);
        $paid = $this->settledShipment($s['shop'], 2000);
        $paid['fulfillment']->events()->create(['status' => 'delivered', 'created_at' => now()->subDays(9)]);
        $other = $this->settledShipment();
        $other['fulfillment']->events()->create(['status' => 'delivered', 'created_at' => now()->subDays(9)]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $unpaidId = DB::table('seller_payouts')->insertGetId(['shop_id' => $s['shop']->id, 'status' => 'unpaid', 'amount_cents' => 10000, 'created_by' => $admin->id]);
        $paidId = DB::table('seller_payouts')->insertGetId(['shop_id' => $s['shop']->id, 'status' => 'paid', 'amount_cents' => 2000, 'created_by' => $admin->id]);
        foreach ([[$s, $unpaidId], [$paid, $paidId]] as [$source, $payoutId]) {
            $entry = DB::table('seller_payable_entries')->where('shipment_settlement_id', $source['settlement']->id)->sole();
            DB::table('seller_payout_items')->insert(['seller_payout_id' => $payoutId, 'seller_payable_entry_id' => $entry->id, 'active_entry_id' => $entry->id, 'amount_cents' => $entry->amount_cents]);
        }
        $this->assertSame(['available_cents' => 10000, 'in_unpaid_batch_cents' => 10000, 'ineligible_held_cents' => 4500], app(SellerPayableBalance::class)->forShop($s['shop']));
    }

    public function test_database_constraints_enforce_sources_one_unpaid_batch_and_active_item(): void
    {
        $s = $this->settledShipment();
        $entry = DB::table('seller_payable_entries')->where('shipment_settlement_id', $s['settlement']->id)->sole();
        $case = $this->returnCase($s, 'refunded');
        $adjustment = $s['settlement']->adjustments()->create(['return_request_id' => $case->id, 'refund_cents' => 1000, 'commission_reversal_cents' => 0, 'seller_debit_cents' => 1000]);
        $adjustmentEntry = app(SellerPayableLedger::class)->postAdjustment($adjustment);
        $base = ['shop_id' => $s['shop']->id, 'amount_cents' => 10000];
        $this->assertRejected(fn () => DB::table('seller_payable_entries')->insert([...$base, 'shipment_settlement_id' => $s['settlement']->id]));
        $this->assertRejected(fn () => DB::table('seller_payable_entries')->insert([...$base, 'settlement_adjustment_id' => $adjustment->id]));
        $this->assertRejected(fn () => DB::table('seller_payable_entries')->insert($base));
        $unposted = $this->settledShipment($s['shop'], 10000, false);
        $unpostedCase = $this->returnCase($unposted, 'refunded');
        $unpostedAdjustment = $unposted['settlement']->adjustments()->create(['return_request_id' => $unpostedCase->id, 'refund_cents' => 1000, 'commission_reversal_cents' => 0, 'seller_debit_cents' => 1000]);
        $this->assertRejected(fn () => DB::table('seller_payable_entries')->insert([...$base, 'shipment_settlement_id' => $unposted['settlement']->id, 'settlement_adjustment_id' => $unpostedAdjustment->id]));
        app(SellerPayableLedger::class)->postSettlement($unposted['settlement']);
        app(SellerPayableLedger::class)->postAdjustment($unpostedAdjustment);
        $this->assertRejected(fn () => DB::table('seller_payable_entries')->where('id', $entry->id)->update(['amount_cents' => 0]));
        $this->assertRejected(fn () => DB::table('seller_payable_entries')->where('id', $entry->id)->delete());
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $payout = ['shop_id' => $s['shop']->id, 'status' => 'unpaid', 'amount_cents' => 10000, 'created_by' => $admin->id];
        $payoutId = DB::table('seller_payouts')->insertGetId($payout);
        $this->assertRejected(fn () => DB::table('seller_payouts')->insert($payout));
        $item = ['seller_payout_id' => $payoutId, 'seller_payable_entry_id' => $entry->id, 'active_entry_id' => $entry->id, 'amount_cents' => 10000];
        DB::table('seller_payout_items')->insert($item);
        $this->assertRejected(fn () => DB::table('seller_payout_items')->insert($item));
        $this->assertRejected(fn () => DB::table('seller_payout_items')->insert([...$item, 'active_entry_id' => $adjustmentEntry]));
        DB::table('seller_payouts')->where('id', $payoutId)->update(['status' => 'cancelled']);
        DB::table('seller_payout_items')->where('seller_payout_id', $payoutId)->update(['active_entry_id' => null]);
        DB::table('seller_payouts')->insert($payout);
        $this->assertDatabaseCount('seller_payouts', 2);
    }

    private function settledShipment(?Shop $shop = null, int $sellerCents = 10000, bool $post = true): array
    {
        $shop ??= Shop::factory()->create();
        $buyer = User::factory()->create();
        $order = Order::create(['user_id' => $buyer->id, 'number' => (string) Str::uuid(), 'status' => 'delivered', 'payment_method' => 'cod', 'ship_to' => 'Address', 'subtotal' => 100, 'shipping_fee' => 0, 'total' => 100]);
        $fulfillment = $order->fulfillments()->create(['shop_id' => $shop->id, 'shop_name' => $shop->name, 'status' => 'delivered', 'subtotal' => 100, 'shipping_fee' => 0]);
        $item = $order->items()->create(['fulfillment_id' => $fulfillment->id, 'name' => 'Item', 'qty' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $settlement = ShipmentSettlement::create(['fulfillment_id' => $fulfillment->id, 'goods_cents' => $sellerCents, 'delivery_cents' => 0, 'commission_bps' => 0, 'commission_cents' => 0, 'seller_cents' => $sellerCents, 'rider_cents' => 0, 'courier_cost_cents' => 0, 'cod_cost_cents' => 0, 'operating_cost_cents' => 0, 'contribution_cents' => 0, 'earned_at' => now()]);
        if ($post) {
            app(SellerPayableLedger::class)->postSettlement($settlement);
        }

        return compact('shop', 'fulfillment', 'item', 'settlement');
    }

    private function returnCase(array $s, string $status = 'pending'): ReturnRequest
    {
        return ReturnRequest::create(['order_item_id' => $s['item']->id, 'quantity' => 1, 'reason' => 'damaged', 'details' => 'Damage reported.', 'requested_amount' => 100, 'status' => $status]);
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The database must reject this row.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
