<?php

namespace App\Support\Watchdog\Checks;

use App\Models\AdminActivity;
use App\Models\User;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;

/**
 * Staff account changes, read from the activity log since the last look: a sign-in from a new browser,
 * a new staff account, a changed role, a reset 2-step login (each sent once). Every staff sign-in goes
 * into the daily digest only.
 */
final class StaffCheck implements Check
{
    private const WATCHED = ['staff.new_device', 'staff.created', 'staff.role_changed', 'staff.2fa_reset', 'staff.removed', 'staff.signed_in'];

    public function key(): string
    {
        return 'security.staff';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $last = State::get('staff_activity_id');
        if (! is_int($last)) {
            State::put('staff_activity_id', (int) AdminActivity::max('id')); // first run: start from now, not from the past

            return [];
        }

        $rows = AdminActivity::with('user:id,name')->where('id', '>', $last)->whereIn('action', self::WATCHED)->orderBy('id')->limit(200)->get();
        foreach ($rows as $row) {
            $finding = $this->finding($row);
            if ($finding !== null) {
                $watchdog->event($finding);
            }
        }
        State::put('staff_activity_id', (int) ($rows->max('id') ?? AdminActivity::where('id', '>', $last)->max('id') ?? $last));

        return [];
    }

    private function finding(AdminActivity $row): ?Finding
    {
        $who = $row->user->name ?? 'The site';
        $subject = $row->subject_type === 'user' ? User::find($row->subject_id) : null;
        $name = $subject->name ?? 'a staff member';
        $isSuper = $subject?->role === 'super_admin';
        $key = "{$this->key()}:{$row->action}:{$row->id}";
        $steps = ['If you did not expect this, open Admin → Staff now.', 'Change your password and reset 2-step login for that account.'];

        return match ($row->action) {
            'staff.new_device' => new Finding($key, $isSuper ? 'warning' : 'info', "{$name} signed in from a new browser",
                "{$name} signed in to the admin from a browser not used before (".($row->details['label'] ?? 'unknown').').'.(($row->details['first'] ?? false) ? ' It is their first sign-in.' : ''),
                $steps, [], 'user', $subject?->id),
            'staff.created' => new Finding($key, 'warning', 'A new staff account was made', "{$who}: {$row->summary}.", $steps, [], 'user', $subject?->id),
            'staff.role_changed' => new Finding($key, 'warning', 'A staff role was changed', "{$who} changed a role: {$row->summary}.", $steps, [], 'user', $subject?->id),
            'staff.2fa_reset' => new Finding($key, 'warning', '2-step login was reset for a staff account', "{$who}: {$row->summary}. At their next sign-in, the password alone is enough to set up a new 2-step login.", $steps, [], 'user', $subject?->id),
            'staff.removed' => new Finding($key, 'info', 'A staff account was removed', "{$who}: {$row->summary}.", [], [], 'user', $subject?->id),
            'staff.signed_in' => new Finding($key, 'info', "{$name} signed in to the admin", "{$name} signed in to the admin.", [], [], 'user', $subject?->id),
            default => null,
        };
    }
}
