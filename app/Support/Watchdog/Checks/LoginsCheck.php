<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Signals;
use App\Support\Watchdog\Watchdog;

/** Many failed sign-ins in 15 minutes: staff (password or 2-step code) = urgent; Google sign-ins of users = warning. */
final class LoginsCheck implements Check
{
    public function key(): string
    {
        return 'security.logins';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $limit = max(1, $limit);
        $found = [];
        $staff = Signals::recent('admin_login_failed', 15);
        if ($staff >= $limit) {
            $found[] = new Finding($this->key().':staff', 'urgent', "{$staff} failed staff sign-ins in 15 minutes",
                "Someone typed a wrong staff password or 2-step code {$staff} times in 15 minutes. Someone may be trying to get into the admin.",
                ['If it was not you or your staff: change your password now.', 'Check Admin → Staff: every account must have 2-step login.', 'Each email + network is already blocked for 5 minutes after 5 tries.'],
                ['failed' => $staff]);
        }
        $users = Signals::recent('login_failed', 15);
        if ($users >= $limit * 3) {
            $found[] = new Finding($this->key().':users', 'warning', "{$users} failed Google sign-ins in 15 minutes",
                "Google sign-in failed {$users} times in 15 minutes. Either Google has a problem or robots are trying.",
                ['Try to sign in yourself.', 'Check GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env if nobody can sign in.'],
                ['failed' => $users]);
        }

        return $found;
    }
}
