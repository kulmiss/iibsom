<?php

namespace App\Support\Watchdog;

use App\Models\User;
use App\Models\WatchdogProblem;
use App\Support\Admin\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * The problem book (PR 94). No spam:
 * - 🔴 urgent: sent at once, again every "reminder hours" while not fixed (not while muted), also in quiet hours;
 * - 🟠 warning: sent once (held during quiet hours and sent when they end, if still open);
 * - 🟢 info: never sent alone, only in the daily digest;
 * - "✅ Fixed" when a problem that was sent is back to normal.
 */
final class Watchdog
{
    public function __construct(private readonly Notifier $notifier, private readonly WatchdogSettings $settings) {}

    /**
     * Store what one check found and close its problems that are not found any more.
     *
     * @param  list<Finding>  $findings
     */
    public function apply(string $check, array $findings): void
    {
        $keys = [];
        foreach ($findings as $finding) {
            $this->raise($finding);
            $keys[] = $finding->key;
        }

        $this->openOf($check)->whereNotIn('key', $keys)->get()->each(fn (WatchdogProblem $p) => $this->close($p, 'auto'));
    }

    public function raise(Finding $finding): WatchdogProblem
    {
        $now = now();
        $problem = WatchdogProblem::open()->where('key', $finding->key)->first();

        if ($problem === null) {
            try {
                $problem = WatchdogProblem::create($this->fields($finding) + [
                    'key' => $finding->key, 'count' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another run opened it a moment ago.
                $problem = WatchdogProblem::open()->where('key', $finding->key)->firstOrFail();
            }
            if ($problem->wasRecentlyCreated) {
                $this->alert($problem);

                return $problem;
            }
        }

        $worse = WatchdogProblem::LEVELS[$finding->level] > WatchdogProblem::LEVELS[$problem->level];
        $problem->fill($this->fields($finding) + ['last_seen_at' => $now]);
        $problem->count++;
        $problem->save();

        if ($worse) {
            $this->alert($problem); // e.g. disk 85% (warning) → 95% (urgent): tell again
        }

        return $problem;
    }

    /** Something that happened once (a new staff browser, the cron came back): stored as already over, sent once. */
    public function event(Finding $finding): WatchdogProblem
    {
        $now = now();
        $problem = WatchdogProblem::create($this->fields($finding) + [
            'key' => $finding->key, 'count' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now, 'fixed_at' => $now, 'fixed_how' => 'event',
        ]);
        $this->alert($problem);

        return $problem;
    }

    /** Staff pressed "Mark as fixed". If the check still finds it, it opens again as a new problem (and is sent again). */
    public function markFixed(WatchdogProblem $problem, User $by): void
    {
        $this->close($problem, 'staff', $by->id, sendFixed: false);
        Activity::log('watchdog.fixed', "Marked as fixed: {$problem->title}", $problem);
    }

    public function mute(WatchdogProblem $problem, int $hours, User $by): void
    {
        $problem->update(['muted_until' => now()->addHours($hours), 'muted_by' => $by->id]);
        Activity::log('watchdog.muted', "Muted for {$hours} hours: {$problem->title}", $problem);
    }

    /** A check that was turned OFF: its open problems close without a message. */
    public function closeQuietly(string $check): void
    {
        $this->openOf($check)->get()->each(fn (WatchdogProblem $p) => $this->close($p, 'off', sendFixed: false));
    }

    /** Sends reminders for urgent problems and warnings held during quiet hours. Called after every run. */
    public function followUp(): void
    {
        $quiet = $this->isQuiet();
        $hours = max(1, $this->settings->all()['reminder_hours']);

        WatchdogProblem::open()->where('pending_alert', true)->get()
            ->each(function (WatchdogProblem $p) use ($quiet) {
                if ($p->level === 'urgent' || ! $quiet) {
                    $this->alert($p);
                }
            });

        WatchdogProblem::open()->where('level', 'urgent')->whereNotNull('last_alert_at')
            ->where('last_alert_at', '<=', now()->subHours($hours))
            ->where(fn ($q) => $q->whereNull('muted_until')->orWhere('muted_until', '<=', now()))
            ->get()->each(fn (WatchdogProblem $p) => $this->send($p, Messages::alert($p, reminder: true)));
    }

    /** History: problems fixed more than 30 days ago are deleted. */
    public static function prune(): int
    {
        return WatchdogProblem::whereNotNull('fixed_at')->where('fixed_at', '<', now()->subDays(config('watchdog.keep_days')))->delete();
    }

    public function isQuiet(?Carbon $at = null): bool
    {
        $options = $this->settings->all();
        if (! $options['quiet_on']) {
            return false;
        }
        $now = ($at ?? now())->copy()->timezone(config('watchdog.timezone'))->format('H:i');
        [$from, $to] = [$options['quiet_from'], $options['quiet_to']];

        return $from <= $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);
    }

    private function alert(WatchdogProblem $problem): void
    {
        if ($problem->level === 'info') {
            return; // daily digest only
        }
        if ($problem->level === 'warning' && $this->isQuiet()) {
            $problem->update(['pending_alert' => true]);

            return;
        }
        $this->send($problem, Messages::alert($problem));
        $problem->update(['pending_alert' => false, 'alerted_at' => $problem->alerted_at ?? now()]);
    }

    /** @param  array{subject: string, body: string}  $message */
    private function send(WatchdogProblem $problem, array $message): void
    {
        $this->notifier->send($message);
        $problem->update(['last_alert_at' => now()]);
    }

    private function close(WatchdogProblem $problem, string $how, ?int $by = null, bool $sendFixed = true): void
    {
        $problem->update(['fixed_at' => now(), 'fixed_how' => $how, 'fixed_by' => $by, 'pending_alert' => false]);

        $quietWarning = $problem->level === 'warning' && $this->isQuiet();
        if ($sendFixed && $problem->alerted_at !== null && ! $quietWarning) {
            $this->notifier->send(Messages::fixed($problem));
        }
    }

    /** @return Builder<WatchdogProblem> */
    private function openOf(string $check)
    {
        return WatchdogProblem::open()->where(fn ($q) => $q->where('key', $check)->orWhere('key', 'like', addcslashes($check, '%_\\').':%'));
    }

    /** @return array<string, mixed> */
    private function fields(Finding $finding): array
    {
        return [
            'area' => WatchdogSettings::definition($finding->check())['area'] ?? 'health',
            'level' => $finding->level,
            'title' => mb_substr($finding->title, 0, 200),
            'message' => $finding->message,
            'steps' => array_slice($finding->steps, 0, 3),
            'data' => $finding->data ?: null,
            'subject_type' => $finding->subjectType,
            'subject_id' => $finding->subjectId,
        ];
    }
}
