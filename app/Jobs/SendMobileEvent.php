<?php

namespace App\Jobs;

use App\Models\MobileEvent;
use App\Services\Mobile\MobileMessaging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendMobileEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 45;

    public function __construct(public int $eventId) {}

    public function backoff(): array
    {
        return [1, 3, 10];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('mobile-event:'.$this->eventId))->releaseAfter(3)->expireAfter(65)];
    }

    public function handle(MobileMessaging $messaging): void
    {
        $messaging->deliver($this->eventId);
    }

    public function failed(?\Throwable $exception): void
    {
        MobileEvent::whereKey($this->eventId)->update(['last_error' => 'Provider delivery failed; check credentials, provider and worker.']);
    }
}
