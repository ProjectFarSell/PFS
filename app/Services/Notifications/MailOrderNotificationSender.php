<?php

namespace App\Services\Notifications;

use App\Contracts\OrderNotificationSender;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderEventMail;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Mail;

class MailOrderNotificationSender implements OrderNotificationSender
{
    public function send(NotificationDelivery $delivery): void
    {
        $message = $delivery->type === 'order_confirmation'
            ? new OrderConfirmationMail($delivery->order()->with('items')->firstOrFail())
            : new OrderEventMail($delivery);

        Mail::mailer(config('notifications.mailer'))
            ->to($delivery->recipient_email, $delivery->recipient_name)
            ->send($message);
    }
}
