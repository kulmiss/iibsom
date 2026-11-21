<?php

namespace App\Support\Translations;

/**
 * Rules for the Somali review sheet (PR 112), shared by the export (max length, notes) and the import (checks).
 */
class ReviewRules
{
    /** Names of categories, fields, places and districts: the admin allows up to this many letters. */
    public const NAME_MAX = 60;

    /**
     * How long the Somali text may be, so it still fits on a phone. Somali is often longer than English,
     * so there is room: twice the English, never less than 30 letters.
     */
    public static function maxLength(string $key, string $english): int
    {
        if (str_starts_with($key, 'catalogue.')) {
            return self::NAME_MAX;
        }
        if (str_starts_with($key, 'help.') || str_starts_with($key, 'pages.')) {
            return max(200, (int) ceil(mb_strlen($english) * 2)); // long texts: plenty of room
        }

        return max(30, (int) ceil(mb_strlen($english) * 2));
    }

    /**
     * The words the site fills in (":name", ":count") and the plural markers ("{0}", "[2,*]", "|").
     *
     * @return array{placeholders: list<string>, plural_parts: int, markers: list<string>}
     */
    public static function shape(string $text): array
    {
        preg_match_all('/:[a-z_]+/i', $text, $p);
        preg_match_all('/\{\d+\}|\[\d+,(?:\d+|\*)\]/', $text, $m);
        // ":Name" and ":name" are the same word (Laravel only changes the first letter).
        $placeholders = array_values(array_unique(array_map('strtolower', $p[0])));
        sort($placeholders);
        $markers = $m[0];
        sort($markers);

        return ['placeholders' => $placeholders, 'plural_parts' => substr_count($text, '|') + 1, 'markers' => $markers];
    }

    /**
     * What is wrong with a reviewed text, in easy English (empty = fine).
     *
     * @return list<string>
     */
    public static function problems(string $key, string $english, string $somali, ?int $max = null): array
    {
        $problems = [];
        $want = self::shape($english);
        $got = self::shape($somali);
        if ($want['placeholders'] !== $got['placeholders']) {
            $missing = array_diff($want['placeholders'], $got['placeholders']);
            $extra = array_diff($got['placeholders'], $want['placeholders']);
            $problems[] = trim(($missing ? 'missing '.implode(', ', $missing).' ' : '').($extra ? 'unknown '.implode(', ', $extra) : ''));
        }
        if ($want['plural_parts'] !== $got['plural_parts'] || $want['markers'] !== $got['markers']) {
            $problems[] = 'the plural parts (|, {0}, [2,*]) must stay as in the English';
        }
        $max ??= self::maxLength($key, $english);
        if (mb_strlen($somali) > $max) {
            $problems[] = 'too long ('.mb_strlen($somali)." letters, max {$max})";
        }
        if (preg_match('/<\s*\/?\s*[a-z]/i', $somali) && ! preg_match('/<\s*\/?\s*[a-z]/i', $english)) {
            $problems[] = 'HTML is not allowed';
        }
        if (! mb_check_encoding($somali, 'UTF-8')) {
            $problems[] = 'broken letters (save the file as UTF-8)';
        }

        return $problems;
    }

    /** A short note for the reviewer: what the site fills in, and what kind of text it is. */
    public static function note(string $key, string $english, string $status): string
    {
        $notes = [$status];
        $shape = self::shape($english);
        if ($shape['placeholders'] !== []) {
            $notes[] = 'keep '.implode(' ', $shape['placeholders']).' (the site fills them in)';
        }
        if ($shape['plural_parts'] > 1) {
            $notes[] = 'one | plural: keep the | and any {0} [2,*]';
        }
        if (str_starts_with($key, 'catalogue.')) {
            $notes[] = 'a name (category, field, city or district): check the spelling';
        }
        if (str_starts_with($key, 'help.') || str_starts_with($key, 'pages.')) {
            $notes[] = 'long text: keep the ## headings, - lists and **bold** marks';
        }

        return implode('; ', $notes);
    }
}
