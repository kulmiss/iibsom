<?php

namespace App\Support\Safety;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Simple, cheap spam and abuse checks for text that customers write.
 * Each check has its own feature switch (group "Safety").
 */
class SpamGuard
{
    /** Name of the hidden bot-trap field. Real people never see or fill it. */
    public const HONEYPOT = 'website';

    /** Stop the request if a robot filled the hidden field. */
    public static function honeypot(Request $request): void
    {
        if (feature('safety.honeypot') && filled($request->input(self::HONEYPOT))) {
            Log::channel('security')->warning('spam.honeypot', ['user_id' => $request->user()?->id, 'route' => $request->route()?->getName()]);
            throw ValidationException::withMessages(['title' => __('ads.safety.bot')]);
        }
    }

    /**
     * Check the ad text. Throws a validation error on the field that has the problem.
     *
     * @param  array<string, string|null>  $texts  field => text, e.g. ['title' => ..., 'description' => ...]
     */
    public static function checkAd(array $texts): void
    {
        foreach ($texts as $field => $text) {
            $text = (string) $text;

            if (feature('safety.block_links') && self::hasLink($text)) {
                throw ValidationException::withMessages([$field => __('ads.safety.links')]);
            }

            if (feature('safety.bad_words') && self::badWord($text) !== null) {
                throw ValidationException::withMessages([$field => __('ads.safety.bad_words')]);
            }
        }
    }

    /** The first bad word found, or null. Whole words only ("class" is fine, "ass" is not). */
    public static function badWord(string $text): ?string
    {
        $clean = self::normalise($text);

        foreach (self::words() as $word) {
            $word = self::normalise($word);
            if ($word === '') {
                continue;
            }
            $pattern = '/(?<![\p{L}\p{N}])'.preg_replace('/\s+/', '\\s+', preg_quote($word, '/')).'(?![\p{L}\p{N}])/u';
            if (preg_match($pattern, $clean)) {
                return $word;
            }
        }

        return null;
    }

    public static function hasLink(string $text): bool
    {
        return (bool) preg_match('~(https?://|www\.|\b[a-z0-9-]{2,}\.(com|net|org|info|xyz|top|site|online|link|ly|io|me|so)\b(/|\s|$))~i', $text);
    }

    /** @return list<string> */
    public static function words(): array
    {
        $words = setting('safety.bad_words') ?? [];

        return array_values(array_filter(array_map('trim', is_array($words) ? $words : explode("\n", (string) $words))));
    }

    /** Lower case, common look-alike letters (sh1t → shit, b!tch → bitch). */
    private static function normalise(string $text): string
    {
        $map = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i'];

        // Only inside a word or at its start ("sh1t", "$hit"), so "$100" and "great!" stay as they are.
        return preg_replace_callback('/(?<=\p{L})[013457@$!](?=\p{L})|(?<![\p{L}\p{N}])[@$](?=\p{L})/u', fn ($m) => $map[$m[0]], Str::lower($text));
    }
}
