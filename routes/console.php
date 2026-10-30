<?php

use App\Services\Orders\DeliveryRequestService;
use App\Services\Notifications\OrderNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
