<?php

use App\Services\Orders\DeliveryRequestService;
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
