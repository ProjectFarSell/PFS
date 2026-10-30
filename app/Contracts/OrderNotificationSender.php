<?php

namespace App\Contracts;

use App\Models\NotificationDelivery;

interface OrderNotificationSender
{
    public function send(NotificationDelivery $delivery): void;
}
