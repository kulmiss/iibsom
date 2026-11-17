<?php

namespace App\Mail;

use App\Models\Ad;
use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** Daily "new ads for your saved search" email, in Somali and English. */
class SavedSearchAlertMail extends Mailable
{
    use Queueable;

    /** @param  Collection<int, Ad>  $ads  newest first (up to 5) */
    public function __construct(public SavedSearch $search, public Collection $ads, public int $total) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "iibsom: {$this->total} new · {$this->search->label}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.saved-search-alert', with: [
            'search' => $this->search,
            'ads' => $this->ads,
            'total' => $this->total,
            'resultsUrl' => $this->search->url(),
            'manageUrl' => lroute('searches'),
        ]);
    }
}
