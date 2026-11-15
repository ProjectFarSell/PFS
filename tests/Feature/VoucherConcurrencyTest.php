<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VoucherConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_independent_checkouts_cannot_spend_the_last_voucher_twice(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This race requires independent MySQL connections.');
        }
        $this->assertSame('farsell_test', DB::connection()->getDatabaseName());
        $admin = User::factory()->create(['role' => 'admin']);
        Voucher::create(['code' => 'LASTONE', 'name' => 'Last redemption', 'type' => 'fixed', 'value' => 1000,
            'per_buyer_limit' => 1, 'usage_limit' => 1, 'is_active' => true, 'created_by' => $admin->id]);
        $workers = [];
        $code = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'farsell_test') { exit(2); }
config(['notifications.enabled' => false, 'mobile.push_enabled' => false, 'mobile.realtime_enabled' => false]);
Illuminate\Support\Facades\DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
Illuminate\Support\Facades\DB::beginTransaction();
// Mimic an API replay transaction whose consistent snapshot predates the winner.
Illuminate\Support\Facades\DB::table('voucher_redemptions')->count();
fwrite(STDOUT, "READY\n"); fflush(STDOUT); fgets(STDIN);
try {
    $buyer = App\Models\User::findOrFail($argv[2]);
    $address = App\Models\Address::findOrFail($argv[3]);
    $product = App\Models\Product::findOrFail($argv[4]);
    $line = (object) ['product' => $product, 'variant' => null, 'variant_options' => null, 'qty' => 1];
    app(App\Services\Orders\PlaceOrder::class)->handle($buyer, $address, collect([$line]), App\Enums\PaymentMethod::Cod, voucherCode: 'LASTONE');
    Illuminate\Support\Facades\DB::commit();
    $result = 'placed';
} catch (Illuminate\Validation\ValidationException $e) {
    Illuminate\Support\Facades\DB::rollBack();
    if (! isset($e->errors()['voucher_code'])) { exit(3); }
    $result = 'rejected';
}
echo json_encode(['result' => $result, 'connection_id' => Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id])."\n";
PHP;
        try {
            for ($i = 0; $i < 2; $i++) {
                $buyer = User::factory()->create();
                $address = Address::factory()->create(['user_id' => $buyer->id]);
                $product = Product::factory()->create(['price' => '500', 'stock' => 3]);
                $input = new InputStream;
                $process = new Process([PHP_BINARY, '-r', $code, base_path(), (string) $buyer->id, (string) $address->id, (string) $product->id], base_path(),
                    ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'farsell_test', 'DB_URL' => ''], $input, 60);
                $process->start();
                $workers[] = [$process, $input, $product];
            }
            $deadline = microtime(true) + 30;
            do {
                $ready = collect($workers)->every(fn ($worker) => str_contains($worker[0]->getOutput(), 'READY'));
                if ($ready) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($ready, 'Both independent checkouts must reach the start barrier.');
            foreach ($workers as [$process, $input]) {
                $input->write("go\n");
                $input->close();
            }
            $results = [];
            foreach ($workers as [$process]) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertNotSame($results[0]['connection_id'], $results[1]['connection_id']);
            $this->assertEqualsCanonicalizing(['placed', 'rejected'], array_column($results, 'result'));
            $this->assertDatabaseCount('voucher_redemptions', 1);
            $this->assertDatabaseCount('orders', 1);
            $this->assertSame(5, $workers[0][2]->fresh()->stock + $workers[1][2]->fresh()->stock);
        } finally {
            foreach ($workers as [$process, $input]) {
                $input->close();
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }
}
