<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A Watchdog alert, digest or test (PR 94): plain text, the same words as the Telegram message. */
class WatchdogMail extends Mailable
{
    use Queueable;

    public function __construct(public string $subjectLine, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.watchdog', with: ['body' => $this->body]);
    }
}
