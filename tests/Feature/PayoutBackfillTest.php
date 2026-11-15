<?php

namespace Tests\Feature;

use App\Models\ReturnRequest;
use App\Models\ShipmentSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PayableScenario;
use Tests\TestCase;

class PayoutBackfillTest extends TestCase
{
    use PayableScenario, RefreshDatabase;

    public function test_dry_run_is_read_only_and_real_backfill_is_idempotent_without_invented_cash_history(): void
    {
        $s = $this->payableScenario();
        $legacyOrder = $s['order']->replicate();
        $legacyOrder->number = 'LEGACY-BACKFILL';
        $legacyOrder->save();
        $legacy = $legacyOrder->fulfillments()->create(['shop_id' => $s['shop']->id, 'shop_name' => $s['shop']->name, 'status' => 'completed', 'subtotal' => '100.00', 'shipping_fee' => '0.00']);
        $orphan = $s['order']->fulfillments()->create(['shop_id' => null, 'shop_name' => 'Historical missing shop', 'status' => 'completed', 'subtotal' => '100.00', 'shipping_fee' => '0.00']);
        foreach ([$legacy, $orphan] as $part) {
            ShipmentSettlement::create(['fulfillment_id' => $part->id, 'goods_cents' => 10000, 'delivery_cents' => 0,
                'commission_bps' => 800, 'commission_cents' => 800, 'seller_cents' => 9200, 'rider_cents' => 0,
                'courier_cost_cents' => 0, 'cod_cost_cents' => 0, 'operating_cost_cents' => 0, 'contribution_cents' => 800, 'earned_at' => now()]);
        }
        $beforeAudit = DB::table('security_audit_events')->count();
        Artisan::call('payouts:backfill', ['--dry-run' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['dry_run' => true, 'eligible' => 1, 'already_imported' => 1, 'unidentifiable' => 1, 'imported' => 0], $report);
        $this->assertDatabaseCount('seller_payable_entries', 1);
        $this->assertSame($beforeAudit, DB::table('security_audit_events')->count());
        Artisan::call('payouts:backfill');
        $this->assertSame(1, json_decode(Artisan::output(), true)['imported']);
        $this->assertDatabaseCount('seller_payable_entries', 2);
        Artisan::call('payouts:backfill');
        $this->assertSame(0, json_decode(Artisan::output(), true)['imported']);
        $this->assertDatabaseCount('seller_payable_entries', 2);
        $this->assertDatabaseCount('seller_payouts', 0);
        $this->assertDatabaseCount('rider_cod_remittances', 0);
    }

    public function test_historical_adjustment_imports_one_negative_entry_without_rewriting_its_settlement(): void
    {
        $s = $this->payableScenario();
        $case = ReturnRequest::create(['order_item_id' => $s['part']->items()->sole()->id, 'quantity' => 1, 'reason' => 'damaged',
            'details' => 'Historical refund already paid.', 'requested_amount' => '50.00', 'approved_amount' => '50.00', 'status' => 'refunded']);
        $settlement = ShipmentSettlement::sole();
        $adjustment = $settlement->adjustments()->create(['return_request_id' => $case->id, 'refund_cents' => 5000, 'commission_reversal_cents' => 400, 'seller_debit_cents' => 4600]);
        Artisan::call('payouts:backfill', ['--dry-run' => true]);
        $this->assertSame(1, json_decode(Artisan::output(), true)['eligible']);
        $this->assertDatabaseCount('seller_payable_entries', 1);
        Artisan::call('payouts:backfill');
        $this->assertDatabaseHas('seller_payable_entries', ['settlement_adjustment_id' => $adjustment->id, 'amount_cents' => -4600]);
        Artisan::call('payouts:backfill');
        $this->assertDatabaseCount('seller_payable_entries', 2);
        $this->assertSame(46000, (int) $settlement->fresh()->seller_cents);
    }
}
