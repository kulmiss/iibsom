<?php

namespace App\Support\Watchdog;

use App\Models\WatchdogProblem;
use Illuminate\Support\Carbon;

/** The words of every alert (easy English): what happened, how bad, what to do first, a link. */
final class Messages
{
    public const ICONS = ['urgent' => '🔴', 'warning' => '🟠', 'info' => '🟢'];

    private const HOW_BAD = [
        'urgent' => 'Urgent: please fix this now.',
        'warning' => 'Warning: please check it today.',
        'info' => 'For your information.',
    ];

    /** @return array{subject: string, body: string} */
    public static function alert(WatchdogProblem $problem, bool $reminder = false): array
    {
        $icon = self::ICONS[$problem->level] ?? '🟠';
        $head = ($reminder ? 'Still not fixed: ' : '').$problem->title;

        $lines = [$icon.' '.$head, '', 'What happened: '.$problem->message, 'How bad: '.(self::HOW_BAD[$problem->level] ?? self::HOW_BAD['warning'])];
        $steps = array_slice($problem->steps ?? [], 0, 3);
        if ($steps !== []) {
            $lines[] = 'What to do first:';
            foreach ($steps as $i => $step) {
                $lines[] = ($i + 1).'. '.$step;
            }
        }
        $lines[] = '';
        $lines[] = 'First seen '.self::time($problem->first_seen_at).($problem->count > 1 ? ", seen {$problem->count} times" : '').'.';
        $lines[] = 'Watchdog page: '.self::link();

        return ['subject' => "iibsom {$icon} {$head}", 'body' => implode("\n", $lines)];
    }

    /** @return array{subject: string, body: string} */
    public static function fixed(WatchdogProblem $problem): array
    {
        $open = $problem->first_seen_at->diffForHumans($problem->fixed_at ?? now(), ['syntax' => Carbon::DIFF_ABSOLUTE, 'parts' => 2]);
        $body = "✅ Fixed: {$problem->title}\n\nBack to normal at ".self::time($problem->fixed_at ?? now())." (it lasted {$open}).\nWatchdog page: ".self::link();

        return ['subject' => "iibsom ✅ Fixed: {$problem->title}", 'body' => $body];
    }

    /** @return array{subject: string, body: string} */
    public static function test(): array
    {
        return [
            'subject' => 'iibsom 🟢 Watchdog test alert',
            'body' => "🟢 Watchdog test alert\n\nThis is a test. If you can read it, Watchdog alerts reach you here.\nWatchdog page: ".self::link(),
        ];
    }

    public static function time(Carbon $at): string
    {
        return $at->copy()->timezone(config('watchdog.timezone'))->format('j M H:i');
    }

    public static function link(): string
    {
        return url('/admin/watchdog');
    }
}
