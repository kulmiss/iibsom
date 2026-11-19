<?php

namespace App\Support\Search;

use App\Search\DatabaseAdSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Words people can be offered while typing a search (switch "search.suggestions").
 * Made only from live ad titles (never from what visitors type), so nothing private ends up here.
 */
class SearchTerms
{
    public const MAX_TERMS = 20000;

    /** Very common words that are no help alone (English + Somali). */
    private const STOP = ['and', 'for', 'the', 'with', 'new', 'used', 'sale', 'iyo', 'oo', 'ah', 'ka', 'ku', 'loo', 'cusub', 'iib', 'kiro', 'in', 'of', 'to'];

    /** Rebuild the list. Returns how many terms were kept. */
    public static function rebuild(): int
    {
        $counts = [];
        DatabaseAdSearch::publicQuery()->select(['ads.id', 'ads.title'])->orderBy('ads.id')
            ->chunk(2000, function ($ads) use (&$counts) {
                foreach ($ads as $ad) {
                    foreach (self::termsOf((string) $ad->title) as $term) {
                        $counts[$term] = ($counts[$term] ?? 0) + 1;
                    }
                }
            });

        // A two-word phrase must be in at least 2 ads (one odd title is no help to others).
        $counts = array_filter($counts, fn (int $ads, string|int $term) => $ads >= 2 || ! str_contains((string) $term, ' '), ARRAY_FILTER_USE_BOTH);
        arsort($counts);
        $rows = [];
        foreach (array_slice($counts, 0, self::MAX_TERMS, true) as $term => $ads) {
            $rows[] = ['term' => (string) $term, 'ads' => $ads];
        }

        DB::transaction(function () use ($rows) {
            DB::table('search_terms')->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('search_terms')->insert($chunk);
            }
        });

        return count($rows);
    }

    /**
     * Single words and two-word phrases of one title, each once.
     *
     * @return list<string>
     */
    public static function termsOf(string $title): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 2 && mb_strlen($w) <= 30));
        $terms = [];
        foreach ($words as $i => $word) {
            if (! in_array($word, self::STOP, true) && ! ctype_digit($word)) {
                $terms[$word] = true;
            }
            if (isset($words[$i + 1]) && ! in_array($word, self::STOP, true) && ! in_array($words[$i + 1], self::STOP, true)) {
                $pair = $word.' '.$words[$i + 1];
                if (mb_strlen($pair) <= 60) {
                    $terms[$pair] = true;
                }
            }
        }

        return array_keys($terms);
    }

    /**
     * Terms that start with what was typed (or have a word that starts with it), most used first.
     *
     * @return list<string>
     */
    public static function suggest(string $typed, int $limit = 6): array
    {
        $typed = Str::lower(trim(preg_replace('/\s+/u', ' ', $typed) ?? ''));
        if (mb_strlen($typed) < 2) {
            return [];
        }
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $typed);

        $starts = DB::table('search_terms')->where('term', 'like', $like.'%')
            ->orderByDesc('ads')->orderBy('term')->limit($limit)->pluck('term')->all();
        if (count($starts) < $limit) { // "corolla" also finds "toyota corolla"
            $inner = DB::table('search_terms')->where('term', 'like', '% '.$like.'%')
                ->whereNotIn('term', $starts)->orderByDesc('ads')->limit($limit - count($starts))->pluck('term')->all();
            $starts = array_merge($starts, $inner);
        }

        return array_values(array_map('strval', $starts));
    }
}
