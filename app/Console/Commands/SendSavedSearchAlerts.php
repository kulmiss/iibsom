<?php

namespace App\Console\Commands;

use App\Mail\SavedSearchAlertMail;
use App\Models\SavedSearch;
use App\Search\AdSearch;
use App\Search\SearchCriteria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Daily (see routes/console.php): one email per saved search that has new
 * matching ads since the last email. Banned users and switched-off
 * categories/cities are skipped. One failed email never stops the others.
 */
class SendSavedSearchAlerts extends Command
{
    protected $signature = 'iibsom:saved-search-alerts';

    protected $description = 'Email users the new ads that match their saved searches';

    public function handle(AdSearch $search): int
    {
        if (! feature('saved_searches')) {
            $this->line('Saved searches switch is OFF.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        $startedAt = now(); // ads published after this wait for tomorrow's run
        SavedSearch::query()->with('user')->orderBy('id')->chunkById(200, function ($searches) use ($search, $startedAt, &$sent, &$failed) {
            foreach ($searches as $saved) {
                if ($saved->user->isBanned() || ! $saved->user->email) {
                    continue;
                }
                $filters = SearchCriteria::toFilters($saved->criteria);
                if ($filters === null) {
                    continue; // its category or city is OFF now
                }

                $page = $search->search($filters + ['published_after' => $saved->checked_until], 5);
                if ($page->total() === 0) {
                    $saved->forceFill(['checked_until' => $startedAt])->save();

                    continue;
                }

                try {
                    Mail::to($saved->user->email)->send(new SavedSearchAlertMail($saved, collect($page->items()), $page->total()));
                    $saved->forceFill(['checked_until' => $startedAt, 'last_sent_at' => now()])->save();
                    $sent++;
                } catch (Throwable $e) {
                    $failed++; // checked_until stays: tomorrow tries again
                    Log::error('saved_search_alert.failed', ['search' => $saved->id, 'error' => $e->getMessage()]);
                }
            }
        });

        $this->info("Sent {$sent} alert(s)".($failed ? ", {$failed} failed (see storage/logs)" : '').'.');

        return $failed > 0 && $sent === 0 ? self::FAILURE : self::SUCCESS;
    }
}
