<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MobileOperationsAlert extends Mailable
{
    public function __construct(public array $health) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'FarSell mobile delivery needs attention');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.mobile-operations-alert');
    }
}
