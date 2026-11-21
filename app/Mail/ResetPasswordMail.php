<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Forgot password" link (PR 105). Valid 60 minutes, works once. Somali and English, like the other customer emails. */
class ResetPasswordMail extends Mailable
{
    use Queueable;

    public function __construct(public string $name, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'iibsom: furaha sirta ah cusbooneysii · reset your password');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reset-password', with: ['name' => $this->name, 'url' => $this->url]);
    }
}
