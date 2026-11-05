<?php

namespace App\Mail;

use App\Models\NotificationDelivery;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderEventMail extends Mailable
{
    public function __construct(public NotificationDelivery $delivery) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'FarSell · '.$this->delivery->payload['order_number'].' · '.$this->delivery->payload['title']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.event');
    }
}
