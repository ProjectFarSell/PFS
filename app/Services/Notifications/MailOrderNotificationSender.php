<?php

namespace App\Services\Notifications;

use App\Contracts\OrderNotificationSender;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderEventMail;
use App\Models\MobileQuote;
use App\Models\NotificationDelivery;
use App\Services\Mobile\MobileMailSafety;
use Illuminate\Support\Facades\Mail;

class MailOrderNotificationSender implements OrderNotificationSender
{
    public function send(NotificationDelivery $delivery): void
    {
        if ($delivery->order_id && MobileQuote::where('order_id', $delivery->order_id)->exists()) {
            app(MobileMailSafety::class)->ensure(config('notifications.mailer'));
        }
        $message = $delivery->type === 'order_confirmation'
            ? new OrderConfirmationMail($delivery->order()->with('items')->firstOrFail())
            : new OrderEventMail($delivery);

        Mail::mailer(config('notifications.mailer'))
            ->to($delivery->recipient_email, $delivery->recipient_name)
            ->send($message);
    }
}
