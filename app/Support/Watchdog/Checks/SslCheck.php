<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Probes\Ssl;
use App\Support\Watchdog\Watchdog;

/** The https certificate ends soon (warning under the limit, urgent under 3 days). Only for https sites. */
final class SslCheck implements Check
{
    public function __construct(private readonly Ssl $ssl) {}

    public function key(): string
    {
        return 'health.ssl';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $url = (string) config('app.url');
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! AppSettingsCheck::isLiveAddress($url)) {
            return [];
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ends = $this->ssl->expiresAt($host);
        if ($ends === null) {
            return [new Finding($this->key().':unreadable', 'warning', 'The SSL certificate could not be read',
                "The Watchdog could not read the https certificate of {$host}.",
                ['Open https://'.$host.' in a browser and look for a lock or a warning.'])];
        }
        $days = (int) floor(now()->diffInDays($ends, false));
        if ($days >= $limit) {
            return [];
        }
        $level = $days < 3 ? 'urgent' : 'warning';
        $title = $days < 0 ? 'The SSL certificate has ended' : "The SSL certificate ends in {$days} days";

        return [new Finding($this->key(), $level, $title,
            "The https certificate of {$host} ends on {$ends->format('j M Y')}. After that, browsers show a big warning and people leave.",
            ['Renew it in your hosting panel (Let\'s Encrypt / AutoSSL), or check that certbot renews (VPS).', 'If Cloudflare is in front, check its SSL settings.'],
            ['days' => $days])];
    }
}
