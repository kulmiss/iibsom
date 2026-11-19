<?php

namespace App\Mail;

use App\Models\Ad;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "A buyer asks you to call" (PR 66), in Somali and English like the other customer emails. */
class CallBackMail extends Mailable
{
    use Queueable;

    public function __construct(public Ad $ad, public string $buyerName, public string $phone) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'iibsom: '.$this->buyerName.' · '.mb_strimwidth($this->ad->title, 0, 60, '…'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.call-back', with: [
            'ad' => $this->ad,
            'buyerName' => $this->buyerName,
            'phone' => $this->phone,
            'tel' => 'tel:'.preg_replace('/[^\d+]/', '', $this->phone),
            'settingsUrl' => lroute('notifications.settings'),
        ]);
    }
}
