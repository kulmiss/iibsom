<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;

/** On the live site: APP_DEBUG=false and APP_ENV=production. */
final class AppSettingsCheck implements Check
{
    public function key(): string
    {
        return 'health.app_settings';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        if (! self::isLiveAddress((string) config('app.url'))) {
            return [];
        }
        $found = [];
        if (! app()->environment('production')) {
            $found[] = new Finding($this->key().':env', 'urgent', 'The live site is not in production mode',
                'APP_ENV is "'.app()->environment().'" on '.parse_url((string) config('app.url'), PHP_URL_HOST).'. Test settings may be on.',
                ['Set APP_ENV=production in .env.', 'Run: php artisan config:cache']);
        }
        if (config('app.debug')) {
            $found[] = new Finding($this->key().':debug', 'urgent', 'Debug mode is ON on the live site',
                'APP_DEBUG is true. Error pages can show secret details to visitors.',
                ['Set APP_DEBUG=false in .env.', 'Run: php artisan config:cache']);
        }

        return $found;
    }

    /** Our own computers and test sites are not "live". */
    public static function isLiveAddress(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost') {
            return false;
        }
        foreach (['.test', '.local', '.localhost', '.example'] as $end) {
            if (str_ends_with($host, $end)) {
                return false;
            }
        }

        return preg_match('/^(staging|stage|dev|test|qa|preview)[.-]/', $host) !== 1;
    }
}
