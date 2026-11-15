<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ShipmentSettlement;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\CreateSellerPayoutBatch;
use App\Services\Orders\SellerPayableBalance;
use App\Services\Orders\SellerPayableLedger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SellerPayoutBatchTest extends TestCase
{
    use DatabaseMigrations;

    public function test_batch_includes_only_eligible_entries_and_its_total_matches_items(): void
    {
        $s = $this->fixture();
        $held = $this->fixture($s['shop'], 3000, false);
        $result = app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin']);
        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('seller_payouts', ['id' => $result['id'], 'amount_cents' => 10000]);
        $this->assertSame(10000, (int) DB::table('seller_payout_items')->where('seller_payout_id', $result['id'])->sum('amount_cents'));
        $this->assertDatabaseMissing('seller_payout_items', ['seller_payable_entry_id' => $held['entry']]);
        $this->assertSame(['id' => $result['id'], 'created' => false], app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin']));
    }

    public function test_nonpositive_balance_is_rejected(): void
    {
        $s = $this->fixture(eligible: false);
        $this->expectException(ValidationException::class);
        app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin']);
    }

    public function test_outstanding_debit_can_make_the_eligible_total_nonpositive(): void
    {
        $s = $this->fixture();
        $case = ReturnRequest::create(['order_item_id' => $s['item']->id, 'quantity' => 1, 'reason' => 'damaged', 'details' => 'Damaged item', 'requested_amount' => '100.00', 'status' => 'refunded']);
        $adjustment = $s['settlement']->adjustments()->create(['return_request_id' => $case->id, 'refund_cents' => 10000, 'commission_reversal_cents' => 0, 'seller_debit_cents' => 10000]);
        app(SellerPayableLedger::class)->postAdjustment($adjustment);
        $this->expectException(ValidationException::class);
        app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin']);
    }

    public function test_sqlite_partial_unique_index_rejects_a_second_unpaid_batch(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The partial unique index is SQLite-specific.');
        }
        $s = $this->fixture();
        app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin']);
        $this->expectException(QueryException::class);
        DB::table('seller_payouts')->insert(['shop_id' => $s['shop']->id, 'status' => 'unpaid', 'amount_cents' => 1, 'created_by' => $s['admin']->id]);
    }

    public function test_mysql_parallel_creators_and_refunds_are_serialized_twenty_times(): void
    {
        $this->requireMysql();
        for ($iteration = 0; $iteration < 20; $iteration++) {
            $s = $this->fixture();
            [$a, $inputA] = $this->worker('create', $s);
            [$b, $inputB] = $this->worker('create', $s);
            $this->release([$a, $b], [$inputA, $inputB]);
            $results = [$this->workerResult($a), $this->workerResult($b)];
            $this->assertNotSame($results[0]['connection_id'], $results[1]['connection_id']);
            $this->assertSame([false, true], collect($results)->pluck('created')->sort()->values()->all());
            $this->assertSame($results[0]['id'], $results[1]['id']);
            $this->assertSame(1, DB::table('seller_payouts')->where('shop_id', $s['shop']->id)->where('status', 'unpaid')->count());
            $this->assertSame(1, DB::table('seller_payout_items')->where('active_entry_id', $s['entry'])->count());
            $this->assertFalse(app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin'])['created']);

            $r = $this->fixture();
            $refundSource = $this->fixture($r['shop']);
            $case = ReturnRequest::create(['order_item_id' => $refundSource['item']->id, 'quantity' => 1, 'reason' => 'damaged', 'details' => 'Damaged item', 'requested_amount' => '20.00', 'approved_amount' => '20.00', 'status' => 'approved']);
            $r['case'] = $case;
            [$creator, $creatorInput] = $this->worker('create', $r);
            [$refund, $refundInput] = $this->worker('refund', $r);
            $this->release([$creator, $refund], [$creatorInput, $refundInput]);
            $this->workerResult($creator);
            $this->workerResult($refund);
            $this->assertSame(18000, (int) DB::table('seller_payable_entries')->where('shop_id', $r['shop']->id)->sum('amount_cents'));
            $batch = DB::table('seller_payouts')->where('shop_id', $r['shop']->id)->sole();
            $this->assertSame((int) $batch->amount_cents, (int) DB::table('seller_payout_items')->where('seller_payout_id', $batch->id)->sum('amount_cents'));
            $this->assertContains((int) $batch->amount_cents, [18000, 10000]);
            $this->assertSame(['available_cents' => 18000, 'in_unpaid_batch_cents' => (int) $batch->amount_cents, 'ineligible_held_cents' => 0], app(SellerPayableBalance::class)->forShop($r['shop']));
        }
    }

    public function test_mysql_second_connection_waits_for_shop_lock_then_observes_batch(): void
    {
        $this->requireMysql();
        $s = $this->fixture();
        config(['database.connections.payout_second' => config('database.connections.mysql')]);
        $second = DB::connection('payout_second');
        DB::beginTransaction();
        try {
            DB::table('shops')->where('id', $s['shop']->id)->lockForUpdate()->first();
            $this->assertNotSame(DB::connection()->getPdo(), $second->getPdo());
            [$worker, $input] = $this->worker('create_timeout', $s);
            $this->release([$worker], [$input]);
            $worker->wait();
            $this->assertFalse($worker->isSuccessful());
            $this->assertStringContainsString('Lock wait timeout', $worker->getErrorOutput());
            $result = app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin']);
            DB::commit();
            $this->assertSame($result['id'], (int) $second->table('seller_payouts')->where('shop_id', $s['shop']->id)->where('status', 'unpaid')->value('id'));
            DB::setDefaultConnection('payout_second');
            $this->assertSame(['id' => $result['id'], 'created' => false], app(CreateSellerPayoutBatch::class)->create($s['shop'], $s['admin']));
        } finally {
            DB::setDefaultConnection('mysql');
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('payout_second');
        }
    }

    private function fixture(?Shop $shop = null, int $amount = 10000, bool $eligible = true): array
    {
        $shop ??= Shop::factory()->create();
        $buyer = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = Order::create(['user_id' => $buyer->id, 'number' => (string) Str::uuid(), 'status' => 'delivered', 'payment_method' => 'cod', 'ship_to' => 'Address', 'subtotal' => 100, 'shipping_fee' => 0, 'total' => 100]);
        $part = $order->fulfillments()->create(['shop_id' => $shop->id, 'shop_name' => $shop->name, 'status' => 'delivered', 'subtotal' => 100, 'shipping_fee' => 0, 'cod_collected_at' => now()]);
        $item = $order->items()->create(['fulfillment_id' => $part->id, 'name' => 'Item', 'qty' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $part->events()->create(['status' => 'delivered', 'created_at' => now()->subDays($eligible ? 8 : 1)]);
        $settlement = ShipmentSettlement::create(['fulfillment_id' => $part->id, 'goods_cents' => $amount, 'delivery_cents' => 0, 'commission_bps' => 0, 'commission_cents' => 0, 'seller_cents' => $amount, 'rider_cents' => 0, 'courier_cost_cents' => 0, 'cod_cost_cents' => 0, 'operating_cost_cents' => 0, 'contribution_cents' => 0, 'earned_at' => now()]);
        $entry = app(SellerPayableLedger::class)->postSettlement($settlement);

        return compact('shop', 'admin', 'item', 'entry', 'settlement');
    }

    private function requireMysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This concurrency test uses independent MySQL connections.');
        }
    }

    private function worker(string $action, array $fixture): array
    {
        $code = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['notifications.enabled' => false]);
if ($argv[2] === 'create_timeout') { Illuminate\Support\Facades\DB::statement('SET SESSION innodb_lock_wait_timeout = 1'); }
fwrite(STDOUT, "READY\n"); fflush(STDOUT); fgets(STDIN);
try {
    $admin = App\Models\User::findOrFail($argv[4]);
    if ($argv[2] === 'create' || $argv[2] === 'create_timeout') {
        $result = app(App\Services\Orders\CreateSellerPayoutBatch::class)->create(App\Models\Shop::findOrFail($argv[3]), $admin);
    } else {
        app(App\Services\Orders\ReturnRequestService::class)->review(App\Models\ReturnRequest::findOrFail($argv[5]), $admin, ['action' => 'refunded', 'refund_reference' => 'CASH', 'note' => 'Paid']);
        $result = ['refunded' => true];
    }
    $result['connection_id'] = (int) Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    fwrite(STDOUT, json_encode($result)."\n");
} catch (Throwable $e) { fwrite(STDERR, get_class($e).': '.$e->getMessage()); exit(1); }
PHP;
        $input = new InputStream;
        $process = new Process([PHP_BINARY, '-r', $code, base_path(), $action, (string) $fixture['shop']->id, (string) $fixture['admin']->id, (string) ($fixture['case']->id ?? 0)], base_path(), null, $input, 60);
        $process->start();

        return [$process, $input];
    }

    private function release(array $processes, array $inputs): void
    {
        $deadline = microtime(true) + 30;
        do {
            $ready = true;
            foreach ($processes as $process) {
                $ready = $ready && str_contains($process->getOutput(), 'READY');
                if (! $process->isRunning() && ! str_contains($process->getOutput(), 'READY')) {
                    $this->fail($process->getErrorOutput());
                }
            }
            if ($ready) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->assertTrue($ready, 'Both independent processes must be ready.');
        foreach ($inputs as $input) {
            $input->write("go\n");
            $input->close();
        }
    }

    private function workerResult(Process $process): array
    {
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));

        return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
    }
}
