<?php

namespace App\Jobs;

use App\Contracts\OrderNotificationSender;
use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('order-notification:'.$this->deliveryId))->releaseAfter(15)->expireAfter(75)];
    }

    public function handle(OrderNotificationSender $sender): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['sent', 'skipped'], true)) return;

        $reason = $this->skipReason($delivery);
        if ($reason !== null) {
            $delivery->update(['status' => 'skipped', 'last_error' => $reason, 'next_retry_at' => null]);

            return;
        }

        $delivery->forceFill([
            'status' => 'sending',
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'next_retry_at' => null,
            'mailer' => config('notifications.mailer'),
        ])->save();

        try {
            $sender->send($delivery);
        } catch (Throwable $exception) {
            $delay = $this->backoff()[min(max($this->attempts() - 1, 0), 2)];
            $delivery->update([
                'status' => 'retrying',
                'last_error' => 'Mail delivery failed. Check the selected mailer and worker logs.',
                'next_retry_at' => now()->addSeconds($delay),
            ]);
            // Transport exceptions can include credentials or message contents.
            Log::warning('Order notification delivery failed.', ['delivery_id' => $delivery->id, 'exception' => $exception::class]);
            throw $exception;
        }

        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null, 'next_retry_at' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()->whereKey($this->deliveryId)->whereNotIn('status', ['sent', 'skipped'])->update([
            'status' => 'failed',
            'last_error' => 'Automatic delivery attempts exhausted or the worker stopped. Check mail settings before retrying.',
            'next_retry_at' => null,
        ]);
    }

    private function skipReason(NotificationDelivery $delivery): ?string
    {
        $delivery->loadMissing(['order', 'recipient', 'fulfillment.shop', 'fulfillment.rider.user']);
        if (! $delivery->order || ! $delivery->recipient) return 'Order or recipient account is no longer available.';
        if (strcasecmp($delivery->recipient->email, $delivery->recipient_email) !== 0) return 'The recipient email address changed after this event.';

        $part = $delivery->fulfillment;
        if ($delivery->type === 'order_confirmation') {
            return $delivery->order->user_id === $delivery->recipient_user_id ? null : 'The order recipient changed.';
        }
        if (! $part) return 'Shipment is no longer available.';

        if ($delivery->type === 'seller_new_order') {
            return $part->shop?->user_id === $delivery->recipient_user_id && $part->status === FulfillmentStatus::Pending
                ? null : 'This shipment has already been processed or its shop owner changed.';
        }
        if (in_array($delivery->type, ['rider_assignment', 'rider_completion'], true)) {
            if ($part->rider?->user_id !== $delivery->recipient_user_id || $part->rider?->user?->role !== UserRole::Rider) {
                return 'The rider assignment is no longer valid.';
            }
            if ($delivery->type === 'rider_assignment') {
                return $part->rider->isApproved() && in_array($part->status, [FulfillmentStatus::Assigned, FulfillmentStatus::InTransit], true)
                    ? null : 'This delivery assignment is no longer active.';
            }

            return $part->status === FulfillmentStatus::Completed ? null : 'This shipment is no longer completed.';
        }
        if (! in_array($delivery->type, ['shipment_update', 'seller_shipment_update'], true)) return 'Unknown notification type.';
        $recipientMatches = $delivery->type === 'shipment_update'
            ? $delivery->order->user_id === $delivery->recipient_user_id
            : $part->shop?->user_id === $delivery->recipient_user_id;
        if (! $recipientMatches) return 'The shipment recipient changed.';

        return $delivery->fulfillment_event_id === $part->events()->reorder()->latest('id')->value('id')
            ? null : 'A newer shipment update superseded this message.';
    }
}
