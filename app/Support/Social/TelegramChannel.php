<?php

namespace App\Support\Social;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** A Telegram channel through a bot that is an admin of it (TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID). */
class TelegramChannel implements SocialNetwork
{
    public function key(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return 'Telegram channel';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.chat_id'));
    }

    public function post(string $text, string $link): string
    {
        $token = (string) config('services.telegram.bot_token');
        $body = [
            'chat_id' => (string) config('services.telegram.chat_id'),
            'text' => mb_substr($text, 0, 3000)."\n".$link,
            'link_preview_options' => ['url' => $link, 'prefer_large_media' => true],
        ];

        try {
            $res = Http::timeout(15)->asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", $body);
        } catch (ConnectionException $e) {
            throw SocialPostFailed::because('Telegram did not answer: '.$e->getMessage(), [$token]);
        }

        if (! $res->successful() || $res->json('ok') !== true) {
            throw SocialPostFailed::because('Telegram said no ('.$res->status().'): '.(string) $res->json('description', 'no reason given'), [$token]);
        }

        return (string) $res->json('result.message_id');
    }
}
