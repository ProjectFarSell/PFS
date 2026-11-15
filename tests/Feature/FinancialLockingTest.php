<?php

namespace Tests\Feature;

use App\Models\ShipmentSettlement;
use App\Services\Orders\FinancialLocks;
use App\Services\Orders\FinancialTransaction;
use App\Services\Orders\SellerPayableLedger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDOException;
use RuntimeException;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class FinancialLockingTest extends TestCase
{
    use RefreshDatabase, ShipmentScenario;

    public function test_deadlocks_are_retried_and_success_is_returned(): void
    {
        $attempts = 0;
        $value = app(FinancialTransaction::class)->run(function () use (&$attempts) {
            if (++$attempts < 3) {
                throw $this->deadlock();
            }

            return 'saved';
        });
        $this->assertSame(3, $attempts);
        $this->assertSame('saved', $value);
    }

    public function test_deadlock_is_rethrown_after_three_attempts(): void
    {
        $attempts = 0;
        try {
            app(FinancialTransaction::class)->run(function () use (&$attempts) {
                $attempts++;
                throw $this->deadlock();
            });
            $this->fail('Expected a deadlock.');
        } catch (QueryException) {
            $this->assertSame(3, $attempts);
        }
    }

    public function test_other_errors_are_not_retried(): void
    {
        $attempts = 0;
        try {
            app(FinancialTransaction::class)->run(function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('other error');
            });
            $this->fail('Expected the original exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('other error', $exception->getMessage());
            $this->assertSame(1, $attempts);
        }
    }

    public function test_settlement_posting_locks_order_then_shop_then_entries(): void
    {
        $s = $this->shipmentScenario();
        $this->scenarioAction($s['rider']->user, $s['part'], 'deliver', ['delivery_note' => 'Delivered.', 'delivery_recipient_name' => 'Buyer', 'delivery_pin' => $s['part']->fresh()->delivery_pin, 'cod_collected' => 1])->assertSessionHasNoErrors();
        $settlement = ShipmentSettlement::create(['fulfillment_id' => $s['part']->id, 'goods_cents' => 50000, 'delivery_cents' => 4900, 'commission_bps' => 800, 'commission_cents' => 4000, 'seller_cents' => 46000, 'rider_cents' => 4000, 'cod_cost_cents' => 700, 'operating_cost_cents' => 1500, 'contribution_cents' => 2700, 'earned_at' => now()]);
        $entryId = app(SellerPayableLedger::class)->postSettlement($settlement);
        $locks = $this->partialMock(FinancialLocks::class);
        $locks->shouldReceive('order')->once()->ordered()->with($s['order']->id)->passthru();
        $locks->shouldReceive('shop')->once()->ordered()->with($s['shop']->id)->passthru();
        $locks->shouldReceive('entries')->once()->ordered()->with($s['shop']->id, [$entryId])->passthru();
        $this->scenarioAction($s['buyer'], $s['part'], 'complete')->assertSessionHasNoErrors();
    }

    private function deadlock(): QueryException
    {
        $error = new PDOException('Deadlock');
        $error->errorInfo = ['40001', 1213, 'Deadlock'];

        return new QueryException('mysql', 'test', [], $error);
    }
}
