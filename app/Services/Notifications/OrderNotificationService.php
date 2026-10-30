<?php

namespace App\Services\Notifications;

use App\Enums\FulfillmentStatus;
use App\Jobs\SendOrderNotification;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderNotificationService
{
    public function orderPlaced(Order $order): void
    {
        if (! config('notifications.enabled')) return;
        $order->loadMissing(['buyer', 'fulfillments.shop.owner']);
        $this->record('order:'.$order->id.':confirmation', 'order_confirmation', $order->buyer, $order, null, null, [
            'title' => 'Order confirmation',
            'order_number' => $order->number,
            'occurred_at' => $order->created_at->toIso8601String(),
        ]);

        foreach ($order->fulfillments as $part) {
            $this->record('shipment:'.$part->id.':new-order', 'seller_new_order', $part->shop?->owner, $order, $part, null, [
                'title' => 'New order for '.$part->shop_name,
                'message' => 'A buyer placed an order. Open your shop orders to accept and prepare this shipment.',
                'order_number' => $order->number,
                'shop_name' => $part->shop_name,
                'occurred_at' => $order->created_at->toIso8601String(),
                'action_path' => route('fulfillments.index', [], false),
                'action_label' => 'Review shop orders',
            ]);
        }
    }

    public function shipmentChanged(FulfillmentEvent $event): void
    {
        if (! config('notifications.enabled')) return;
        $event->loadMissing(['fulfillment.order.buyer', 'fulfillment.shop.owner', 'fulfillment.rider.user']);
        $part = $event->fulfillment;
        $order = $part->order;
        $status = FulfillmentStatus::from($event->status);
        $base = [
            'order_number' => $order->number,
            'shop_name' => $part->shop_name,
            'status_label' => $status->label(),
            'occurred_at' => $event->created_at->toIso8601String(),
        ];

        $this->record('event:'.$event->id.':buyer', 'shipment_update', $order->buyer, $order, $part, $event, [
            ...$base,
            'title' => 'Shipment update: '.$status->label(),
            'message' => match ($status) {
                FulfillmentStatus::Accepted => 'The seller has accepted your order and is preparing the items.',
                FulfillmentStatus::Ready => 'Your items are ready for rider pickup.',
                FulfillmentStatus::Assigned => 'A rider has been assigned to this shipment.',
                FulfillmentStatus::InTransit => 'Your rider has collected this shipment and is on the way.',
                FulfillmentStatus::Delivered => 'Your shipment was marked delivered. Confirm receipt after checking your items.',
                FulfillmentStatus::Completed => 'You confirmed receipt. You can now review your products and delivery.',
                FulfillmentStatus::Rejected => 'The seller could not fulfill this shipment. Open your order for the updated total and details.',
                default => 'Your shipment has been updated.',
            },
            'detail' => $status === FulfillmentStatus::Rejected ? $part->rejection_reason : null,
            'action_path' => route('orders.show', $order->id, false),
            'action_label' => 'View your order',
        ]);

        if ($status === FulfillmentStatus::Assigned) {
            $this->record('event:'.$event->id.':rider', 'rider_assignment', $part->rider?->user, $order, $part, $event, [
                ...$base,
                'title' => 'You have a delivery assignment',
                'message' => 'You have been assigned a shipment from '.$part->shop_name.'. Sign in to review pickup and delivery details.',
                'action_path' => route('fulfillments.index', [], false),
                'action_label' => 'Open your deliveries',
            ]);
        }

        if (in_array($status, [FulfillmentStatus::Delivered, FulfillmentStatus::Completed], true)) {
            $this->record('event:'.$event->id.':seller', 'seller_shipment_update', $part->shop?->owner, $order, $part, $event, [
                ...$base,
                'title' => 'Shop shipment: '.$status->label(),
                'message' => $status === FulfillmentStatus::Completed ? 'The buyer confirmed receipt of your shipment.' : 'The rider marked your shipment delivered.',
                'action_path' => route('fulfillments.index', [], false),
                'action_label' => 'Open shop orders',
            ]);
        }

        if ($status === FulfillmentStatus::Completed && $part->rider_id !== null) {
            $this->record('event:'.$event->id.':rider-completion', 'rider_completion', $part->rider?->user, $order, $part, $event, [
                ...$base,
                'title' => 'Delivery completed',
                'message' => 'The buyer confirmed receipt. PHP '.number_format((float) $part->shipping_fee, 2).' has been recorded in your earnings ledger.',
                'action_path' => route('rider.earnings.index', [], false),
                'action_label' => 'View earnings',
            ]);
        }
    }

    public function dispatchPending(int $limit = 100): int
    {
        $count = 0;
        foreach (NotificationDelivery::query()->where('status', 'pending')->oldest('id')->limit($limit)->pluck('id') as $id) {
            $count += (int) $this->enqueue($id);
        }

        return $count;
    }

    public function enqueue(int $id): bool
    {
        if (! config('notifications.enabled')) return false;
        try {
            return DB::transaction(function () use ($id) {
                $delivery = NotificationDelivery::query()->whereKey($id)->where('status', 'pending')->lockForUpdate()->first();
                if (! $delivery) return false;
                $delivery->update(['status' => 'queued', 'queued_at' => now(), 'next_retry_at' => null]);

                // The domain transaction has already committed. With the database queue,
                // the queue row and this status now commit together on the same connection.
                Bus::dispatch((new SendOrderNotification($id))
                    ->onConnection(config('notifications.connection'))
                    ->onQueue(config('notifications.queue'))
                    ->beforeCommit());

                return true;
            }, 3);
        } catch (Throwable $exception) {
            NotificationDelivery::query()->whereKey($id)->where('status', 'pending')->update([
                'status' => 'pending', 'last_error' => 'Queue unavailable. The scheduler will try to enqueue this message again.',
            ]);
            Log::warning('Order notification could not be queued.', ['delivery_id' => $id, 'exception' => $exception::class]);

            return false;
        }
    }

    public function retry(NotificationDelivery $delivery): bool
    {
        $updated = NotificationDelivery::query()->whereKey($delivery->id)->whereIn('status', ['failed', 'pending'])->update([
            'status' => 'pending', 'last_error' => null, 'next_retry_at' => null,
        ]);

        return $updated && $this->enqueue($delivery->id);
    }

    private function record(string $key, string $type, ?User $recipient, Order $order, ?Fulfillment $part, ?FulfillmentEvent $event, array $payload): void
    {
        if (! $recipient || ! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) return;

        // This row commits with the order/event; failed enqueueing leaves a recoverable pending record.
        $delivery = NotificationDelivery::query()->firstOrCreate(['event_key' => $key], [
            'type' => $type,
            'recipient_user_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'recipient_name' => $recipient->name,
            'order_id' => $order->id,
            'fulfillment_id' => $part?->id,
            'fulfillment_event_id' => $event?->id,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        DB::afterCommit(fn () => $this->enqueue($delivery->id));
    }
}
