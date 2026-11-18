<?php

namespace App\Support\Demo;

use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use App\Support\Ads\AdCounters;
use App\Support\Files\PrivateFiles;
use App\Support\Search\SearchTerms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Everything made by the DemoSeeder has is_demo = true.
 * deleteAll() removes exactly those records (and their photo files), nothing else.
 */
class DemoData
{
    /** @return array{ads:int, users:int, reports:int} */
    public static function counts(): array
    {
        return [
            'ads' => Ad::withTrashed()->where('is_demo', true)->count(),
            'users' => User::where('is_demo', true)->count(),
            'reports' => Report::where('is_demo', true)->count(),
        ];
    }

    /** @return array{ads:int, users:int, reports:int} what was deleted */
    public static function deleteAll(?int $keepUserId = null): array
    {
        $files = [];

        $receipts = [];
        $counts = DB::transaction(function () use (&$files, &$receipts, $keepUserId) {
            $reports = Report::where('is_demo', true)->delete();
            $ads = 0;
            Ad::withTrashed()->where('is_demo', true)->with('photos')->chunkById(200, function ($chunk) use (&$ads, &$files) {
                foreach ($chunk as $ad) {
                    foreach ($ad->photos as $photo) {
                        foreach ($photo->variants ?? [] as $formats) {
                            foreach ($formats as $path) {
                                $files[$photo->disk][$path] = true;
                            }
                        }
                    }
                    Ad::withoutEvents(fn () => $ad->forceDelete());
                    $ads++;
                }
            });
            // TEST-mode boost payments made by demo users go with them (with their ledger lines and boosts),
            // so the books still balance. A real payment is never deleted: that user is then kept.
            $demoUserIds = User::where('is_demo', true)->pluck('id');
            $testPayments = Payment::whereIn('user_id', $demoUserIds)->where('is_test', true)->get(['id', 'receipt_path']);
            if ($testPayments->isNotEmpty()) {
                $ids = $testPayments->pluck('id');
                DB::table('ad_boosts')->whereIn('payment_id', $ids)->delete();
                DB::table('ledger_entries')->whereIn('payment_id', $ids)->delete();
                Payment::whereIn('id', $ids)->delete();
                $receipts = $testPayments->pluck('receipt_path')->filter()->all();
            }

            // Demo staff go too (never the person who is deleting).
            $users = User::where('is_demo', true)->when($keepUserId, fn ($q) => $q->whereKeyNot($keepUserId))
                ->whereDoesntHave('payments')->delete();
            AdminActivity::where('is_demo', true)->delete();

            return compact('ads', 'users', 'reports');
        });

        // Files after the database: if this fails, nothing in the database points to them any more.
        foreach ($receipts as $receipt) {
            rescue(fn () => PrivateFiles::delete($receipt), report: false);
        }
        foreach ($files as $disk => $paths) {
            foreach (array_chunk(array_keys($paths), 500) as $chunk) {
                rescue(fn () => Storage::disk($disk)->delete($chunk), report: false);
            }
        }

        AdCounters::refreshAll();
        SearchTerms::rebuild(); // no suggestions for words of ads that are gone

        return $counts;
    }
}
