<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Notifier;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;
use App\Support\Watchdog\WatchdogSettings;

/**
 * The daily mail test, only when email is set up (a real mailer, the email channel ON and an address).
 * The daily digest email is the test when it went out in the last 26 hours (PR 95); otherwise a short
 * test mail is sent. If it fails, Telegram tells you.
 */
final class MailCheck implements Check
{
    public function __construct(private readonly Notifier $notifier, private readonly WatchdogSettings $settings) {}

    public function key(): string
    {
        return 'health.mail';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $channels = $this->settings->channels();
        if (! self::mailerSet() || ! Notifier::emailReady($channels)) {
            return [];
        }
        $digest = State::get('digest_email');
        if (is_array($digest) && (int) ($digest['at'] ?? 0) >= now()->subHours(26)->getTimestamp()) {
            $sent = (bool) ($digest['ok'] ?? false);
        } else {
            $sent = $this->notifier->email($channels['email_to'], [
                'subject' => 'iibsom 🟢 Watchdog daily mail test',
                'body' => "🟢 Daily mail test\n\nThe site can send email. Nothing to do.",
            ]);
        }

        return $sent ? [] : [new Finding($this->key(), 'warning', 'The site cannot send email',
            'The daily test mail could not be sent. Sign-in links, alerts and the weekly summary do not arrive.',
            ['Check MAIL_HOST, MAIL_USERNAME and MAIL_PASSWORD in .env.', 'Ask your host whether outgoing mail is blocked.'])];
    }

    public static function mailerSet(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true);
    }
}
