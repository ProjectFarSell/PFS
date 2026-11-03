<?php

use App\Jobs\OperationalHeartbeat;
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
