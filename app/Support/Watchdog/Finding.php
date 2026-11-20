<?php

namespace App\Support\Watchdog;

/**
 * Something a check found. Texts are easy English, written by the check from fixed words and numbers:
 * never passwords, tokens, email addresses, phone numbers or what a visitor typed.
 */
final class Finding
{
    /**
     * @param  string  $key  The check key, or "check:part" when one check finds several things (e.g. "ads.price:123").
     * @param  string  $level  urgent | warning | info
     * @param  list<string>  $steps  What to do first (1–3 short steps).
     * @param  array<string, scalar|null>  $data  Small numbers for the page (never personal data).
     */
    public function __construct(
        public readonly string $key,
        public readonly string $level,
        public readonly string $title,
        public readonly string $message,
        public readonly array $steps = [],
        public readonly array $data = [],
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
    ) {}

    public function check(): string
    {
        return explode(':', $this->key, 2)[0];
    }
}
