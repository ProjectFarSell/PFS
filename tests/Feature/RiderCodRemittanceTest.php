<?php

namespace Tests\Feature;

use App\Models\RiderCodRemittance;
use App\Services\Orders\RiderCodBalance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PayableScenario;
use Tests\TestCase;

class RiderCodRemittanceTest extends TestCase
{
    use PayableScenario, RefreshDatabase;

    public function test_collected_cod_is_separate_from_earnings_and_remittance_is_limited_to_cash_owed(): void
    {
        $s = $this->payableScenario();
        $this->assertSame(['collected_cents' => 54900, 'remitted_cents' => 0, 'owed_cents' => 54900], app(RiderCodBalance::class)->forRider($s['rider']));
        $this->get(route('admin.remittances.index'))->assertOk();
        $this->get(route('admin.remittances.show', $s['rider']))->assertOk()->assertSee('549.00');
        $payload = ['amount' => '500.00', 'received_at' => now()->toDateTimeString(), 'note' => 'Cash counted at office.'];
        $this->post(route('admin.remittances.store', $s['rider']), $payload)->assertSessionHasNoErrors();
        $this->assertSame(4900, app(RiderCodBalance::class)->forRider($s['rider'])['owed_cents']);
        $this->post(route('admin.remittances.store', $s['rider']), $payload)->assertSessionHasErrors('amount');
        $this->post(route('admin.remittances.store', $s['rider']), [...$payload, 'amount' => '0.00'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('rider_cod_remittances', 1);
        $this->assertDatabaseHas('rider_earnings', ['fulfillment_id' => $s['part']->id, 'amount' => '40.00']);
        $this->assertDatabaseHas('security_audit_events', ['action' => 'rider_cod_remittance.recorded']);
        $this->actingAs($s['rider']->user)->get(route('rider.cod.index'))->assertOk()->assertViewHas('balance', fn ($value) => $value['owed_cents'] === 4900);
    }

    public function test_void_preserves_history_and_restores_cash_balance_once(): void
    {
        $s = $this->payableScenario();
        $this->post(route('admin.remittances.store', $s['rider']), ['amount' => '549.00', 'received_at' => now()->toDateTimeString(), 'note' => 'Receipt entered for wrong handover.'])->assertSessionHasNoErrors();
        $row = RiderCodRemittance::sole();
        $this->post(route('admin.remittances.void', $row), ['void_reason' => ''])->assertSessionHasErrors('void_reason');
        $this->post(route('admin.remittances.void', $row), ['void_reason' => 'Receipt did not represent cash received.'])->assertSessionHasNoErrors();
        $this->assertSame(54900, app(RiderCodBalance::class)->forRider($s['rider'])['owed_cents']);
        $this->assertDatabaseHas('rider_cod_remittances', ['id' => $row->id, 'amount_cents' => 54900, 'voided_by' => $s['admin']->id]);
        $this->post(route('admin.remittances.void', $row), ['void_reason' => 'Repeat'])->assertSessionHasErrors('remittance');
        $this->assertDatabaseHas('security_audit_events', ['action' => 'rider_cod_remittance.voided']);
    }

    public function test_external_demo_and_uncollected_shipments_do_not_add_to_cod_owed(): void
    {
        $s = $this->payableScenario();
        $s['part']->update(['delivery_method' => 'lalamove']);
        $this->assertSame(0, app(RiderCodBalance::class)->forRider($s['rider'])['collected_cents']);
        $s['part']->update(['delivery_method' => 'internal']);
        $s['order']->update(['payment_method' => 'gateway_stub']);
        $this->assertSame(0, app(RiderCodBalance::class)->forRider($s['rider'])['collected_cents']);
        $s['order']->update(['payment_method' => 'cod']);
        $s['part']->fresh()->update(['cod_collected_at' => null]);
        $this->assertSame(0, app(RiderCodBalance::class)->forRider($s['rider'])['collected_cents']);
    }

    public function test_recorded_rows_cannot_be_edited_or_deleted_through_sql(): void
    {
        $s = $this->payableScenario();
        $this->post(route('admin.remittances.store', $s['rider']), ['amount' => '50.01', 'received_at' => now()->toDateTimeString(), 'note' => 'Counted.'])->assertSessionHasNoErrors();
        $row = RiderCodRemittance::sole();
        foreach (['edit', 'delete'] as $action) {
            try {
                $query = DB::table('rider_cod_remittances')->where('id', $row->id);
                $action === 'edit' ? $query->update(['amount_cents' => 1]) : $query->delete();
                $this->fail('Financial history must be retained.');
            } catch (QueryException) {
                $this->assertDatabaseHas('rider_cod_remittances', ['id' => $row->id, 'amount_cents' => 5001]);
            }
        }
    }
}
