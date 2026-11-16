<?php

namespace App\Support\Phone;

/**
 * Somali mobile numbers.
 * Accepts what people type: "061 5 123456", "615123456", "+252 61 5123456", "00252615123456".
 * Saves one format: +252 + 9 digits (e.g. +252615123456).
 * Mobile numbers start with 6, 7 or 9 (Hormuud 61/77, Somtel 62/65, Golis 90, ...).
 */
final class SomaliPhone
{
    public const COUNTRY_CODE = '+252';

    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (str_starts_with($digits, '00252')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '252') && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = substr($digits, 1);
        }

        if (! preg_match('/^[679]\d{8}$/', $digits)) {
            return null;
        }

        return self::COUNTRY_CODE.$digits;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** "+252615123456" → "+252 61 512 3456" for display. */
    public static function format(string $e164): string
    {
        $n = substr($e164, 4);

        return sprintf('+252 %s %s %s', substr($n, 0, 2), substr($n, 2, 3), substr($n, 5));
    }

    /** For wa.me links: digits only. */
    public static function whatsappNumber(string $e164): string
    {
        return ltrim($e164, '+');
    }
}
