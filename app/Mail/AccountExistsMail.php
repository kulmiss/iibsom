<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Somebody signed up with an email that already has an account (PR 105). Tells the owner; nobody is signed in. Somali and English, like the other customer emails. */
class AccountExistsMail extends Mailable
{
    use Queueable;

    public function __construct(public string $loginUrl, public string $forgotUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'iibsom: akoon ayaad horey u lahayd · you already have an account');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.account-exists', with: ['loginUrl' => $this->loginUrl, 'forgotUrl' => $this->forgotUrl]);
    }
}
