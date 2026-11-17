<?php

namespace App\Support\Privacy;

/** Crawlers, previews and scripts: never counted in stats or clicks. */
class Bots
{
    private const PATTERN = '/bot|crawl|spider|slurp|preview|lighthouse|headless|facebookexternalhit|curl|wget|python|scrapy/i';

    /** No browser name at all counts as a bot too. */
    public static function is(?string $userAgent): bool
    {
        $agent = trim((string) $userAgent);

        return $agent === '' || preg_match(self::PATTERN, $agent) === 1;
    }
}
