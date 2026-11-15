<?php

namespace Tests\Feature;

use App\Models\ReturnRequest;
use App\Models\SellerPayout;
use App\Services\Orders\SellerPayableBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PayableScenario;
use Tests\TestCase;

class SellerPayoutActionsTest extends TestCase
{
    use PayableScenario, RefreshDatabase;

    public function test_admin_creates_and_pays_a_manual_batch_once_with_audited_reference(): void
    {
        $s = $this->payableScenario();
        $this->post(route('admin.payouts.store', $s['shop']))->assertRedirect(route('admin.payouts.show', $s['shop']));
        $batch = SellerPayout::sole();
        $this->assertSame(46000, $batch->amount_cents);
        $this->get(route('admin.payouts.index'))->assertOk()->assertSee($s['shop']->name);
        $this->get(route('admin.payouts.show', $s['shop']))->assertOk()->assertSee('460.00');
        $data = ['payment_reference' => 'BANK-001', 'paid_at' => now()->subMinute()->toDateTimeString()];
        $this->post(route('admin.payouts.pay', $batch), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_payouts', ['id' => $batch->id, 'status' => 'paid', 'paid_by' => $s['admin']->id, 'payment_reference' => 'BANK-001']);
        $this->assertDatabaseHas('security_audit_events', ['action' => 'seller_payout.paid', 'actor_id' => $s['admin']->id]);
        $this->post(route('admin.payouts.pay', $batch), $data)->assertSessionHasErrors('payout');
        $this->post(route('admin.payouts.cancel', $batch), ['cancellation_reason' => 'Wrong record'])->assertSessionHasErrors('payout');
        $this->post(route('admin.payouts.store', $s['shop']))->assertSessionHasErrors('payout');
        $this->assertSame(1, DB::table('seller_payout_items')->whereNotNull('active_entry_id')->count());
        $this->assertSame(0, app(SellerPayableBalance::class)->forShop($s['shop'])['available_cents']);
    }

    public function test_cancel_releases_entries_without_deleting_history_and_rebuild_is_allowed(): void
    {
        $s = $this->payableScenario();
        $batch = $this->payoutBatch($s);
        $this->post(route('admin.payouts.cancel', $batch), ['cancellation_reason' => ''])->assertSessionHasErrors('cancellation_reason');
        $this->post(route('admin.payouts.cancel', $batch), ['cancellation_reason' => 'Reconcile against new bank reference.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_payouts', ['id' => $batch->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('seller_payout_items', ['seller_payout_id' => $batch->id, 'active_entry_id' => null, 'amount_cents' => 46000]);
        $this->assertDatabaseHas('security_audit_events', ['action' => 'seller_payout.cancelled']);
        $replacement = $this->payoutBatch($s);
        $this->assertNotSame($batch->id, $replacement->id);
        $this->assertSame(46000, $replacement->amount_cents);
    }

    public function test_new_refund_debit_rejects_stale_payment_and_is_included_when_rebuilt(): void
    {
        $s = $this->payableScenario();
        $batch = $this->payoutBatch($s);
        $this->refundPayable($s);
        $this->post(route('admin.payouts.pay', $batch), ['payment_reference' => 'STALE', 'paid_at' => now()->toDateTimeString()])->assertSessionHasErrors('payout');
        $this->assertSame('unpaid', $batch->fresh()->status);
        $this->post(route('admin.payouts.cancel', $batch), ['cancellation_reason' => 'Include the refund debit.'])->assertSessionHasNoErrors();
        $this->assertSame(41400, $this->payoutBatch($s)->amount_cents);
    }

    public function test_open_return_or_changed_item_sum_prevents_payment(): void
    {
        $s = $this->payableScenario();
        $batch = $this->payoutBatch($s);
        $case = ReturnRequest::create(['order_item_id' => $s['part']->items()->sole()->id, 'quantity' => 1,
            'reason' => 'damaged', 'details' => 'Checking damage.', 'requested_amount' => '50.00', 'status' => 'pending']);
        $payment = ['payment_reference' => 'BANK', 'paid_at' => now()->toDateTimeString()];
        $this->post(route('admin.payouts.pay', $batch), $payment)->assertSessionHasErrors('payout');
        $this->assertDatabaseCount('settlement_adjustments', 0);
        $case->update(['status' => 'rejected']);
        DB::table('seller_payout_items')->where('seller_payout_id', $batch->id)->update(['amount_cents' => 1]);
        $this->post(route('admin.payouts.pay', $batch), $payment)->assertSessionHasErrors('payout');
        $this->assertSame('unpaid', $batch->fresh()->status);
    }

    public function test_refund_after_payment_becomes_negative_future_balance_without_rewriting_paid_batch(): void
    {
        $s = $this->payableScenario();
        $batch = $this->payoutBatch($s);
        $this->post(route('admin.payouts.pay', $batch), ['payment_reference' => 'PAID', 'paid_at' => now()->toDateTimeString()])->assertSessionHasNoErrors();
        $this->refundPayable($s);
        $this->assertSame(46000, $batch->fresh()->amount_cents);
        $this->assertSame(-4600, app(SellerPayableBalance::class)->forShop($s['shop'])['available_cents']);
        $this->actingAs($s['seller'])->get(route('seller.payouts.index'))->assertOk()->assertSee('-46.00')->assertSee('PAID');
    }

    public function test_missing_delivery_and_payment_fields_are_rejected(): void
    {
        $s = $this->payableScenario();
        $batch = $this->payoutBatch($s);
        $this->post(route('admin.payouts.pay', $batch), [])->assertSessionHasErrors(['payment_reference', 'paid_at']);
        DB::table('fulfillment_events')->where('fulfillment_id', $s['part']->id)->where('status', 'delivered')->delete();
        $this->post(route('admin.payouts.pay', $batch), ['payment_reference' => 'BANK', 'paid_at' => now()->toDateTimeString()])->assertSessionHasErrors('payout');
        $this->get(route('admin.payouts.needs-review'))->assertOk()->assertSee($s['order']->number);
    }
}
