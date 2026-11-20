<?php

namespace App\Support\Watchdog;

use App\Mail\WatchdogMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a Watchdog message by email and/or Telegram (each can be ON/OFF in the settings).
 * A channel that fails is written to the log by its kind only (never the token or the address).
 */
final class Notifier
{
    public function __construct(private readonly WatchdogSettings $settings) {}

    /**
     * @param  array{subject: string, body: string}  $message
     * @param  array{email_on: bool, email_to: string, telegram_on: bool, telegram_chat_id: string, telegram_token: string}|null  $channels  null = from the settings
     * @return array{email: bool|null, telegram: bool|null} null = channel off or not set up
     */
    public function send(array $message, ?array $channels = null): array
    {
        $channels ??= $this->settings->channels();

        return [
            'email' => self::emailReady($channels) ? $this->email($channels['email_to'], $message) : null,
            'telegram' => self::telegramReady($channels) ? $this->telegram($channels['telegram_token'], $channels['telegram_chat_id'], $message['body']) : null,
        ];
    }

    /** @param  array{email_on: bool, email_to: string, telegram_on: bool, telegram_chat_id: string, telegram_token: string}  $channels */
    public static function emailReady(array $channels): bool
    {
        return $channels['email_on'] && filter_var($channels['email_to'], FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @param  array{email_on: bool, email_to: string, telegram_on: bool, telegram_chat_id: string, telegram_token: string}  $channels */
    public static function telegramReady(array $channels): bool
    {
        return $channels['telegram_on'] && $channels['telegram_token'] !== '' && $channels['telegram_chat_id'] !== '';
    }

    /** @param  array{subject: string, body: string}  $message */
    public function email(string $to, array $message): bool
    {
        try {
            Mail::to($to)->send(new WatchdogMail($message['subject'], $message['body']));

            return true;
        } catch (Throwable $e) {
            Log::warning('watchdog.email_failed', ['error' => class_basename($e)]);

            return false;
        }
    }

    public function telegram(string $token, string $chatId, string $text): bool
    {
        try {
            $response = Http::asForm()->timeout(10)->connectTimeout(5)
                ->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => mb_substr($text, 0, 4000), // Telegram's limit is 4096
                    'disable_web_page_preview' => 'true',
                ]);
            if (! $response->successful()) {
                Log::warning('watchdog.telegram_failed', ['status' => $response->status()]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('watchdog.telegram_failed', ['error' => class_basename($e)]);

            return false;
        }
    }
}
