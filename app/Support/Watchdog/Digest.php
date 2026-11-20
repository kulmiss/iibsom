<?php

namespace App\Support\Watchdog;

use App\Models\WatchdogProblem;
use App\Support\Reports\WeeklySummary;
use App\Support\Watchdog\Checks\SpeedCheck;
use Carbon\CarbonImmutable;

/**
 * The daily digest (PR 95): one short message at the digest time (08:00 Mogadishu by default) with
 * yesterday's numbers, the open problems and the "please check" items. On Mondays it also has last week.
 * The numbers come from the same code as the weekly summary email (WeeklySummary).
 */
final class Digest
{
    private const SHOW_PROBLEMS = 8;

    public function __construct(private readonly Notifier $notifier, private readonly WatchdogSettings $settings) {}

    /** Sends today's digest once, at or after the digest time. */
    public function sendIfDue(): bool
    {
        $now = CarbonImmutable::now(config('watchdog.timezone'));
        $today = $now->format('Y-m-d');
        if ($now->format('H:i') < $this->settings->all()['digest_time'] || State::get('digest_day') === $today) {
            return false;
        }
        State::put('digest_day', $today);
        $sent = $this->notifier->send($this->build($now));
        if ($sent['email'] !== null) {
            State::put('digest_email', ['at' => $now->getTimestamp(), 'ok' => $sent['email']]); // the daily mail test (Checks\MailCheck)
        }

        return true;
    }

    /** @return array{subject: string, body: string} */
    public function build(CarbonImmutable $now): array
    {
        $demo = feature('site.demo_data');
        $from = $now->startOfDay()->subDay();
        $to = $from->addDay();
        $day = WeeklySummary::numbers($from, $to, $demo);
        $speed = SpeedCheck::averageForDay($from->format('Y-m-d'));

        $lines = ['🟢 iibsom daily digest, '.$now->format('D j M'), '', 'Yesterday ('.$from->format('D j M').'):'];
        $lines[] = "• New users: {$day['new_users']}";
        $lines[] = "• New ads: {$day['new_ads']} (approved {$day['approved']}, rejected {$day['rejected']})";
        $lines[] = "• Reports: {$day['reports']}";
        $lines[] = '• Top categories: '.self::list(WeeklySummary::topCategories($from, $to, $demo));
        $lines[] = '• Top places: '.self::list(WeeklySummary::topCities($from, $to, $demo));
        $lines[] = '• Errors: '.ErrorCounter::today($from->format('Y-m-d'));
        $lines[] = '• Site speed: '.($speed === null ? 'not measured' : "{$speed} ms on average");

        $open = WatchdogProblem::open()->get();
        $check = $open->whereNotNull('subject_id')->where('level', 'info');
        $problems = $open->diff($check)->sortByDesc(fn (WatchdogProblem $p) => WatchdogProblem::LEVELS[$p->level] * 1e10 + $p->last_seen_at->getTimestamp());
        $lines[] = '';
        if ($problems->isEmpty()) {
            $lines[] = '✅ No open problems.';
        } else {
            $count = fn (string $level) => $problems->where('level', $level)->count();
            $lines[] = "Open problems: 🔴 {$count('urgent')}  🟠 {$count('warning')}  🟢 {$count('info')}";
            foreach ($problems->take(self::SHOW_PROBLEMS) as $p) {
                $lines[] = Messages::ICONS[$p->level].' '.$p->title.' (since '.Messages::time($p->first_seen_at).')';
            }
            if ($problems->count() > self::SHOW_PROBLEMS) {
                $lines[] = '… and '.($problems->count() - self::SHOW_PROBLEMS).' more on the Watchdog page.';
            }
        }
        if ($check->isNotEmpty()) {
            $lines[] = "Please check: {$check->count()} item(s) (for example very low prices).";
        }

        $events = WatchdogProblem::where('fixed_how', 'event')->whereBetween('first_seen_at', [$from->utc(), $to->utc()])->get();
        if ($events->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Happened yesterday:';
            foreach ($events->groupBy('title')->take(self::SHOW_PROBLEMS) as $title => $same) {
                $lines[] = '• '.$title.($same->count() > 1 ? " ({$same->count()}×)" : '');
            }
        }

        if ($now->isMonday()) {
            $week = WeeklySummary::numbers($from->subDays(6), $to, $demo);
            $problemsWeek = WatchdogProblem::where('first_seen_at', '>=', $to->subWeek()->utc())
                ->where(fn ($q) => $q->whereNull('fixed_how')->orWhere('fixed_how', '!=', 'event'));
            $lines[] = '';
            $lines[] = 'Last 7 days:';
            $lines[] = "• New users: {$week['new_users']}, new ads: {$week['new_ads']}, reports: {$week['reports']}";
            $lines[] = '• Problems found: '.$problemsWeek->count();
        }

        $lines[] = '';
        $lines[] = 'Watchdog page: '.Messages::link();

        return ['subject' => 'iibsom 🟢 Daily digest, '.$now->format('j M'), 'body' => implode("\n", $lines)];
    }

    /** @param  list<array{name: string, total: int}>  $rows */
    private static function list(array $rows): string
    {
        return $rows === [] ? 'none' : implode(', ', array_map(fn ($r) => "{$r['name']} ({$r['total']})", $rows));
    }
}
