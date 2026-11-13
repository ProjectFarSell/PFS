<?php

use App\Jobs\OperationalHeartbeat;
use App\Models\SignInChallenge;
use App\Services\Notifications\OrderNotificationService;
use App\Services\Operations\DatabaseRecovery;
use App\Services\Operations\OperationalStatus;
use App\Services\Orders\DeliveryRequestService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('riders:dispatch', function () {
    app(DeliveryRequestService::class)->dispatchPending();
    $this->info('Ready shipments checked for rider offers.');
})->purpose('Offer ready shipments to available local riders');

Schedule::command('riders:dispatch')->everyMinute()->withoutOverlapping();

Artisan::command('notifications:dispatch {--limit=100}', function () {
    $limit = (int) $this->option('limit');
    if ($limit < 1 || $limit > 1000) {
        $this->error('Limit must be between 1 and 1000.');

        return 1;
    }
    $count = app(OrderNotificationService::class)->dispatchPending($limit);
    $this->info($count.' pending notification(s) queued.');

    return 0;
})->purpose('Queue pending order notifications after an enqueueing failure');

Schedule::command('notifications:dispatch')->everyMinute()->withoutOverlapping();

Schedule::call(fn () => SignInChallenge::where('expires_at', '<', now()->subDay())->delete())->daily()->name('prune-sign-in-challenges')->withoutOverlapping();

Artisan::command('ops:heartbeat', function () {
    Cache::put('ops.scheduler.last_seen', now()->timestamp, 600);
    OperationalHeartbeat::dispatch()->onConnection(config('notifications.connection'))->onQueue(config('notifications.queue'));
    $this->info('Scheduler heartbeat recorded; worker heartbeat queued.');
})->purpose('Record scheduler activity and check the actual notification worker');
Schedule::command('ops:heartbeat')->everyMinute()->withoutOverlapping();

Artisan::command('ops:status {--json}', function () {
    $status = app(OperationalStatus::class)->inspect();
    if ($this->option('json')) {
        $this->line(json_encode($status, JSON_PRETTY_PRINT));
    } else {
        $this->table(['Check', 'Value'], collect($status)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'OK' : 'ATTENTION') : ($value ?? 'unavailable')])->values()->all());
    }

    return $status['healthy'] ? 0 : 1;
})->purpose('Inspect database, notification failures, and background-process heartbeats');

Artisan::command('ops:backup {--include-files}', function () {
    try {
        $name = app(DatabaseRecovery::class)->create((bool) $this->option('include-files'));
        $this->info('Encrypted backup created: '.$name);
        $this->comment('Local files only; cloud storage and APP_KEY must be backed up separately.');

        return 0;
    } catch (Throwable $exception) {
        $this->error('Backup failed ('.$exception::class.'). Check BACKUP_PASSWORD, ZIP AES-256 support, permissions, and the SQLite database engine. Use the provider runbook for MySQL.');

        return 1;
    }
})->purpose('Create a consistent, encrypted SQLite backup in private storage');

Artisan::command('ops:restore-check {file}', function () {
    try {
        $this->line(json_encode(app(DatabaseRecovery::class)->verify($this->argument('file')), JSON_PRETTY_PRINT));

        return 0;
    } catch (Throwable $exception) {
        $this->error('Isolated restore check failed ('.$exception::class.'). Check the filename, backup password, checksums, and archive integrity.');

        return 1;
    }
})->purpose('Verify encrypted backup checksums and restore into a temporary SQLite database, never the live database');

Artisan::command('notifications:work {--once} {--stop-when-empty}', function () {
    return $this->call('queue:work', [
        'connection' => config('notifications.connection'),
        '--queue' => config('notifications.queue'),
        '--tries' => 4,
        '--timeout' => 30,
        '--sleep' => 3,
        '--once' => (bool) $this->option('once'),
        '--stop-when-empty' => (bool) $this->option('stop-when-empty'),
    ]);
})->purpose('Process the configured order notification queue');
use App\Jobs\MobileHeartbeat;
use App\Models\MobileEvent;
use App\Models\User;
use App\Services\Mobile\MobileEvents;
use App\Services\Mobile\MobileFixtures;
use App\Services\Mobile\MobileMaintenance;
use App\Services\Mobile\MobileMessaging;
use Illuminate\Support\Str;

Artisan::command('mobile:work {--once} {--stop-when-empty}', function () {
    return $this->call('queue:work', ['connection' => config('mobile.connection'), '--queue' => config('mobile.queue'), '--tries' => 4, '--timeout' => 60, '--sleep' => 1, '--once' => (bool) $this->option('once'), '--stop-when-empty' => (bool) $this->option('stop-when-empty')]);
})->purpose('Process mobile push and real-time outbox jobs');

Artisan::command('mobile:dispatch', function () {
    $events = app(MobileEvents::class);
    $events->expireOffers();
    $count = 0;
    foreach (MobileEvent::whereNull('completed_at')->where('expires_at', '>', now())->oldest('id')->limit(100)->pluck('id') as $id) {
        $count += (int) $events->enqueue($id);
    }
    $this->info($count.' mobile event(s) queued.');
    app(MobileMessaging::class)->alert();
})->purpose('Recover mobile events after a queue outage and invalidate expired offers');
Schedule::command('mobile:dispatch')->everyMinute()->withoutOverlapping();

Artisan::command('mobile:status {--check}', function () {
    $health = app(MobileMessaging::class)->health();
    $this->line(json_encode($health, JSON_PRETTY_PRINT));

    return $this->option('check') && ! $health['healthy'] ? 1 : 0;
})->purpose('Inspect mobile provider flags, pending events and failures without secrets');

Artisan::command('mobile:prune', function () {
    $this->line(json_encode(app(MobileMaintenance::class)->prune(), JSON_PRETTY_PRINT));
    $this->call('sanctum:prune-expired', ['--hours' => 24]);
})->purpose('Prune expired mobile security records and eligible orphan/private proofs');
Schedule::command('mobile:prune')->daily()->withoutOverlapping();

Artisan::command('mobile:fixtures {--reset}', function () {
    if ($this->option('reset')) {
        $connection = DB::connection();
        $path = realpath($connection->getDatabaseName());
        $root = realpath(storage_path('app/mobile-qa'));
        $workspace = realpath(base_path());
        $normalize = fn ($value) => strtolower(str_replace('\\', '/', $value));
        if (! app()->environment(['local', 'testing']) || $connection->getDriverName() !== 'sqlite' || ! $path || ! $root || ! $workspace || ! str_starts_with($normalize($root), $normalize($workspace).'/') || ! str_starts_with($normalize($path), $normalize($root).'/')) {
            $this->error('Reset is allowed only for an isolated SQLite database beneath storage/app/mobile-qa, never the normal or staging database.');

            return 1;
        }
        $this->call('migrate:fresh', ['--force' => true]);
    }
    try {
        $this->line(json_encode(app(MobileFixtures::class)->seed(), JSON_PRETTY_PRINT));

        return 0;
    } catch (Throwable $exception) {
        $this->error('Fixture setup refused or failed ('.$exception::class.'). Check the non-production fixture configuration.');

        return 1;
    }
})->purpose('Seed explicit mobile QA accounts and all shipment states; never production');

Artisan::command('mobile:test-event {user}', function () {
    abort_unless(app()->environment(['local', 'testing', 'staging']), 403);
    $user = User::where('public_id', $this->argument('user'))->firstOrFail();
    abort_unless(in_array($user->role->value, ['buyer', 'rider'], true) && ! $user->suspended_at, 403);
    $event = app(MobileEvents::class)->record('test:'.Str::uuid(), 'shipment.status_changed', [$user->id], []);
    $this->info($event ? 'Generic test event recorded. No order or payment was created.' : 'Mobile events are disabled.');
})->purpose('Record an explicitly requested generic mobile event on a non-production environment');
Artisan::command('mobile:heartbeat', function () {
    if (config('mobile.push_enabled') || config('mobile.realtime_enabled')) {
        MobileHeartbeat::dispatch()->onConnection(config('mobile.connection'))->onQueue(config('mobile.queue'));
    }
})->purpose('Check the actual mobile queue worker without sending provider messages');
Schedule::command('mobile:heartbeat')->everyMinute()->withoutOverlapping();
