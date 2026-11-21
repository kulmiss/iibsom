<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Confirm your email: the link from the sign-up (PR 105). Somali and English, like the other customer emails. */
class ConfirmEmailMail extends Mailable
{
    use Queueable;

    public function __construct(public string $name, public string $url, public int $hours) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'iibsom: xaqiiji iimaylkaaga · confirm your email');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.confirm-email', with: ['name' => $this->name, 'url' => $this->url, 'hours' => $this->hours]);
    }
}
