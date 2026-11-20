<?php

namespace App\Support\Watchdog;

use App\Support\Settings\SettingsManager;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * The owner's Watchdog choices: channels, quiet hours, digest time, and every check ON/OFF + limit.
 * Saved in the "watchdog" setting; the Telegram token is saved encrypted and is never sent to the browser.
 * A copy of the channels is kept (encrypted) in the state file, so an alert can still go out while the database is down.
 *
 * @phpstan-type Options array{email_on: bool, email_to: string, telegram_on: bool, telegram_chat_id: string, quiet_on: bool, quiet_from: string, quiet_to: string, digest_time: string, reminder_hours: int, heartbeat_url: string, checks: array<string, array{on: bool, limit: int|null}>}
 */
final class WatchdogSettings
{
    public function __construct(private readonly SettingsManager $settings) {}

    /** @return Options */
    public function all(): array
    {
        $saved = (array) $this->settings->get('watchdog', []);
        $options = array_merge(config('watchdog.defaults'), array_intersect_key($saved, config('watchdog.defaults')));
        $options['reminder_hours'] = (int) $options['reminder_hours'];

        $checks = [];
        foreach (config('watchdog.checks') as $key => $check) {
            $own = (array) ($saved['checks'][$key] ?? []);
            $checks[$key] = [
                'on' => (bool) ($own['on'] ?? $check['on']),
                'limit' => array_key_exists('limit', $check) ? (int) ($own['limit'] ?? $check['limit']) : null,
            ];
        }
        $options['checks'] = $checks;

        /** @var Options $options */
        return $options;
    }

    public function isOn(string $check): bool
    {
        return $this->all()['checks'][$check]['on'] ?? false;
    }

    public function limit(string $check): int
    {
        return (int) ($this->all()['checks'][$check]['limit'] ?? self::definition($check)['limit'] ?? 0);
    }

    /**
     * One check from config/watchdog.php. (Check keys have a dot, like "health.disk", so config("watchdog.checks.health.disk")
     * would look for checks → health → disk and find nothing.)
     *
     * @return array<string, mixed>
     */
    public static function definition(string $check): array
    {
        return (array) (config('watchdog.checks')[$check] ?? []);
    }

    /** @param  array<string, mixed>  $values  Already validated (see the admin controller). */
    public function save(array $values, ?int $userId): void
    {
        $saved = (array) $this->settings->get('watchdog', []);
        $this->settings->set('watchdog', array_merge($saved, $values), $userId);
        $this->rememberChannels();
    }

    public function saveToken(string $token, ?int $userId): void
    {
        $this->settings->set('watchdog.telegram_token', $token === '' ? '' : Crypt::encryptString($token), $userId);
        $this->rememberChannels();
    }

    public function telegramToken(): string
    {
        $stored = (string) $this->settings->get('watchdog.telegram_token', '');
        try {
            return $stored === '' ? '' : Crypt::decryptString($stored);
        } catch (Throwable) {
            return ''; // APP_KEY changed: the owner enters the token again
        }
    }

    /**
     * Where alerts go, read from the database; when it is down, from the last copy in the state file.
     *
     * @return array{email_on: bool, email_to: string, telegram_on: bool, telegram_chat_id: string, telegram_token: string}
     */
    public function channels(): array
    {
        try {
            $all = $this->all();

            return [
                'email_on' => $all['email_on'], 'email_to' => $all['email_to'],
                'telegram_on' => $all['telegram_on'], 'telegram_chat_id' => $all['telegram_chat_id'],
                'telegram_token' => $this->telegramToken(),
            ];
        } catch (Throwable) {
            return self::channelsFromFile();
        }
    }

    /** Keeps an encrypted copy of the channels for when the database is down (called after every save and run). */
    public function rememberChannels(): void
    {
        State::put('channels', Crypt::encryptString((string) json_encode($this->channels())));
    }

    /** @return array{email_on: bool, email_to: string, telegram_on: bool, telegram_chat_id: string, telegram_token: string} */
    public static function channelsFromFile(): array
    {
        $none = ['email_on' => false, 'email_to' => '', 'telegram_on' => false, 'telegram_chat_id' => '', 'telegram_token' => ''];
        try {
            $copy = json_decode(Crypt::decryptString((string) State::get('channels', '')), true);

            if (! is_array($copy)) {
                return $none;
            }

            return [
                'email_on' => (bool) ($copy['email_on'] ?? false),
                'email_to' => (string) ($copy['email_to'] ?? ''),
                'telegram_on' => (bool) ($copy['telegram_on'] ?? false),
                'telegram_chat_id' => (string) ($copy['telegram_chat_id'] ?? ''),
                'telegram_token' => (string) ($copy['telegram_token'] ?? ''),
            ];
        } catch (Throwable) {
            return $none;
        }
    }
}
