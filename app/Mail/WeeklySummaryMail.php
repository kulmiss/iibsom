<?php

namespace App\Mail;

use App\Support\Reports\WeeklySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** @phpstan-import-type Summary from WeeklySummary */
class WeeklySummaryMail extends Mailable
{
    use Queueable;

    /** @param  Summary  $summary */
    public function __construct(public array $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'iibsom weekly summary: '.$this->summary['from']->format('j M').' – '.$this->summary['to']->subDay()->format('j M Y'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.weekly-summary', with: ['s' => $this->summary]);
    }
}
