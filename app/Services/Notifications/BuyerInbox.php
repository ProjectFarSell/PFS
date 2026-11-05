<?php

namespace App\Services\Notifications;

use App\Enums\UserRole;
use App\Models\BuyerNotification;
use App\Models\Order;

class BuyerInbox
{
    public function record(Order $order, string $key, string $title, string $message): void
    {
        if ($order->buyer?->role !== UserRole::Buyer) {
            return;
        }

        BuyerNotification::query()->firstOrCreate(['event_key' => $key], [
            'user_id' => $order->user_id, 'order_id' => $order->id,
            'title' => $title, 'message' => $message,
        ]);
    }
}
