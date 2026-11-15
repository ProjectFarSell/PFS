<?php

namespace Tests\Feature;

use App\Models\RiderCodRemittance;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PayableScenario;
use Tests\TestCase;

class PayoutRoleIsolationTest extends TestCase
{
    use PayableScenario, RefreshDatabase;

    public function test_every_financial_admin_route_rejects_other_roles(): void
    {
        $s = $this->payableScenario();
        $batch = $this->payoutBatch($s);
        $this->post(route('admin.remittances.store', $s['rider']), ['amount' => '1.00', 'received_at' => now()->toDateTimeString(), 'note' => 'Cash counted.'])->assertSessionHasNoErrors();
        $remittance = RiderCodRemittance::sole();
        $get = ['admin.payouts.index' => [], 'admin.payouts.show' => [$s['shop']], 'admin.remittances.index' => [],
            'admin.remittances.show' => [$s['rider']], 'admin.marketplace-settings.edit' => [], 'admin.payouts.needs-review' => []];
        $post = ['admin.payouts.store' => [$s['shop']], 'admin.payouts.pay' => [$batch], 'admin.payouts.cancel' => [$batch],
            'admin.remittances.store' => [$s['rider']], 'admin.remittances.void' => [$remittance]];
        foreach ([$s['buyer'], $s['seller'], $s['rider']->user] as $user) {
            $this->actingAs($user);
            foreach ($get as $route => $params) {
                $this->get(route($route, $params))->assertForbidden();
            }
            foreach ($post as $route => $params) {
                $this->post(route($route, $params), [])->assertForbidden();
            }
            $this->put(route('admin.marketplace-settings.update'), [])->assertForbidden();
        }
    }

    public function test_seller_history_and_rider_cash_are_scoped_to_current_owner_despite_query_parameters(): void
    {
        $s = $this->payableScenario();
        $this->payoutBatch($s);
        $other = User::factory()->seller()->create();
        Shop::factory()->create(['user_id' => $other->id]);
        $this->actingAs($other)->get(route('seller.payouts.index', ['shop_id' => $s['shop']->id]))->assertOk()
            ->assertViewHas('payouts', fn ($rows) => $rows->isEmpty())->assertViewHas('balance', fn ($balance) => $balance['available_cents'] === 0);
        $otherRider = User::factory()->create(['role' => 'rider']);
        $profile = $otherRider->riderProfile()->create(['status' => 'approved', 'vehicle_type' => 'motorcycle', 'license_no' => 'OTHER-QA', 'city' => 'Quezon City']);
        $this->actingAs($otherRider)->get(route('rider.cod.index', ['rider_id' => $s['rider']->id]))->assertOk()
            ->assertViewHas('rider', fn ($rider) => $rider->id === $profile->id)->assertViewHas('balance', fn ($balance) => $balance['owed_cents'] === 0);
        $this->actingAs($s['buyer'])->get(route('seller.payouts.index'))->assertForbidden();
        $this->get(route('rider.cod.index'))->assertForbidden();
    }

    public function test_financial_writes_keep_recent_admin_confirmation_gate(): void
    {
        $s = $this->payableScenario();
        config(['security.sensitive_confirmation_required' => true]);
        $this->post(route('admin.payouts.store', $s['shop']))->assertRedirect(route('security.index'));
        $this->assertDatabaseCount('seller_payouts', 0);
        $this->withSession(['security.confirmed_user' => $s['admin']->id, 'security.confirmed_at' => now()->timestamp])
            ->post(route('admin.payouts.store', $s['shop']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('seller_payouts', 1);
    }
}
