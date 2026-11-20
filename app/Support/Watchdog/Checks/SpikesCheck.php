<?php

namespace App\Support\Watchdog\Checks;

use App\Models\Ad;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Facades\DB;

/** One user, or one phone number on several accounts, posting many ads in one hour. Demo ads are not counted. */
final class SpikesCheck implements Check
{
    public function key(): string
    {
        return 'ads.spikes';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $limit = max(2, $limit);
        $found = [];
        $since = now()->subHour();

        $users = Ad::where('created_at', '>=', $since)->where('is_demo', false)
            ->groupBy('user_id')->havingRaw('count(*) >= ?', [$limit])
            ->selectRaw('user_id, count(*) as total')->toBase()->get();
        foreach ($users as $row) {
            $found[] = new Finding("{$this->key()}:user:{$row->user_id}", 'warning', "One user posted {$row->total} ads in one hour",
                "User #{$row->user_id} posted {$row->total} ads in the last hour (your limit is {$limit}). It can be a shop, or spam.",
                ['Open the seller and look at the ads.', 'If it is spam: ban the user (the ads come down too).'],
                ['ads' => (int) $row->total], 'user', (int) $row->user_id);
        }

        // The same phone number on several accounts (the number itself is never put in the message).
        $phones = DB::table('ads')->join('users', 'users.id', '=', 'ads.user_id')
            ->where('ads.created_at', '>=', $since)->where('ads.is_demo', false)->whereNotNull('users.phone')
            ->groupBy('users.phone')->havingRaw('count(*) >= ? and count(distinct ads.user_id) > 1', [$limit])
            ->selectRaw('min(ads.user_id) as user_id, count(*) as total, count(distinct ads.user_id) as accounts')->get();
        foreach ($phones as $row) {
            $found[] = new Finding("{$this->key()}:phone:{$row->user_id}", 'warning', "One phone number posted {$row->total} ads in one hour",
                "One phone number, used on {$row->accounts} accounts, posted {$row->total} ads in the last hour (first account: user #{$row->user_id}).",
                ['Open the user and compare the accounts with the same number.', 'If it is spam: ban them and block the number.'],
                ['ads' => (int) $row->total, 'accounts' => (int) $row->accounts], 'user', (int) $row->user_id);
        }

        return $found;
    }
}
