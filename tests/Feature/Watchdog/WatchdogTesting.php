<?php

namespace Tests\Feature\Watchdog;

use App\Support\Watchdog\Probes\Disk;
use App\Support\Watchdog\WatchdogSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;

/** Shared set-up for the Watchdog tests: a temporary state file, faked email + Telegram, channels filled in. */
trait WatchdogTesting
{
    protected const TOKEN = '123456:SECRET-bot-token-xyz';

    protected const CHAT = '-100987654';

    protected string $statePath;

    /** What the site's own pages answer to the speed check (tests change it). */
    protected ?\Closure $pageAnswer = null;

    protected function setUpWatchdog(): void
    {
        $this->statePath = sys_get_temp_dir().'/iibsom-watchdog-'.bin2hex(random_bytes(6)).'.json';
        config(['watchdog.state_path' => $this->statePath]);
        Mail::fake();
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
            '*' => fn ($request) => $this->pageAnswer ? ($this->pageAnswer)($request) : Http::response('ok'),
        ]);

        // This computer's own disk must not decide the tests: 50% full unless a test says otherwise.
        $this->mock(Disk::class, function (MockInterface $m) {
            $m->shouldReceive('space')->andReturn(['free' => 50 * 1024 ** 3, 'total' => 100 * 1024 ** 3]);
            $m->shouldReceive('folderSize')->andReturn(0);
        });

        $settings = app(WatchdogSettings::class);
        $settings->save(['email_to' => 'owner@iibsom.test', 'telegram_chat_id' => self::CHAT], null);
        $settings->saveToken(self::TOKEN, null);
    }

    protected function tearDown(): void
    {
        if (isset($this->statePath) && is_file($this->statePath)) {
            unlink($this->statePath);
        }
        parent::tearDown();
    }

    /** @return list<string> Texts sent to Telegram. */
    protected function telegrams(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.telegram.org'))
            ->map(fn ($pair) => (string) $pair[0]->data()['text'])
            ->values()->all();
    }

    /** @param  array<string, mixed>  $values */
    protected function watchdogSettings(array $values): void
    {
        app(WatchdogSettings::class)->save($values, null);
    }

    /** @param  array<string, array{on?: bool, limit?: int}>  $checks */
    protected function checks(array $checks): void
    {
        $saved = (array) setting('watchdog', []);
        $merged = array_replace_recursive((array) ($saved['checks'] ?? []), $checks);
        app(WatchdogSettings::class)->save(['checks' => $merged], null);
    }
}
