<?php

namespace App\Support\Search;

use App\Models\Ad;
use Illuminate\Support\Facades\DB;

/**
 * The ad's detail values (brand, model, year, mileage, yes/no…) in their own indexed table, so filters like
 * "Toyota" or "year 2010–2015" stay fast with many ads (PR 90, switch "search.indexed_filters").
 *
 * One row per value: `text_value` for exact matches (select, text, yes/no as '1'/'0'), `num_value` for ranges.
 * The ad's `fields` (JSON) stays the source; this table is rebuilt from it on every change, and fully with
 * `php artisan iibsom:filters:rebuild`. Text over MAX_TEXT characters is not kept: the search then uses the
 * old way (JSON) for that value.
 */
final class AdFilters
{
    public const MAX_TEXT = 120;

    /** @return list<array{ad_id: int, key: string, text_value: ?string, num_value: ?int}> */
    public static function rows(int $adId, mixed $fields): array
    {
        $rows = [];
        foreach (is_array($fields) ? $fields : [] as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key)) {
                continue;
            }
            [$text, $num] = match (true) {
                is_bool($value) => [$value ? '1' : '0', null],
                is_int($value) => [(string) $value, $value],
                is_float($value) => [(string) $value, (int) $value],
                is_string($value) => [mb_strlen($value) <= self::MAX_TEXT ? $value : null, self::integer($value)],
                default => [null, null],
            };
            if ($text !== null || $num !== null) {
                $rows[] = ['ad_id' => $adId, 'key' => $key, 'text_value' => $text, 'num_value' => $num];
            }
        }

        return $rows;
    }

    public static function sync(Ad $ad): void
    {
        DB::transaction(function () use ($ad) {
            DB::table('ad_filters')->where('ad_id', $ad->id)->delete();
            $rows = self::rows($ad->id, $ad->fields);
            if ($rows !== []) {
                DB::table('ad_filters')->insert($rows);
            }
        });
    }

    /** Rebuild for every ad (in chunks). @return int ads done */
    public static function rebuildAll(?callable $progress = null): int
    {
        $done = 0;
        DB::table('ads')->select(['id', 'fields'])->orderBy('id')->chunkById(500, function ($ads) use (&$done, $progress) {
            $ids = $ads->pluck('id')->all();
            $rows = [];
            foreach ($ads as $ad) {
                $fields = is_string($ad->fields) ? json_decode($ad->fields, true) : $ad->fields;
                array_push($rows, ...self::rows((int) $ad->id, $fields));
            }
            DB::transaction(function () use ($ids, $rows) {
                DB::table('ad_filters')->whereIn('ad_id', $ids)->delete();
                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::table('ad_filters')->insert($chunk);
                }
            });
            $done += count($ids);
            if ($progress) {
                $progress(count($ids));
            }
        });

        return $done;
    }

    /** A whole number written as text ("2012", "-5"), or null. Same idea as MySQL comparing text with a number. */
    private static function integer(string $value): ?int
    {
        $v = trim($value);

        return preg_match('/^-?\d{1,15}$/', $v) ? (int) $v : null;
    }
}
