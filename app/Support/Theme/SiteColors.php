<?php

namespace App\Support\Theme;

/**
 * The website's main colour (PR 88, setting "site.brand_color", switch "site.custom_color").
 *
 * The owner picks ONE colour. It becomes "brand-700" (top bar, buttons, links) exactly as picked; every other
 * shade is made from it in the OKLCH colour space, at the same lightness steps as the iibsom blue, so a
 * green or red site looks as balanced as the blue one. Dark mode gets its own darker tints and a light text
 * colour. A colour is only accepted when white text on it is easy to read (contrast 4.5:1 or more).
 */
class SiteColors
{
    public const DEFAULT = '#1d6bca'; // = brand-700 in resources/css/app.css

    public const MIN_CONTRAST = 4.5;

    /** The iibsom blue scale (resources/css/app.css): the steps the new scale copies. */
    private const LIGHT = [
        50 => '#eef5fd', 100 => '#dbe9fa', 200 => '#bcd6f6', 300 => '#8ab8f2', 600 => '#2a7be0',
        700 => '#1d6bca', 800 => '#1a5bab', 900 => '#14467f', 950 => '#0c2c52',
    ];

    /** Dark mode tints (the light shades turned dark) and the light text colour used on dark. */
    private const DARK = [50 => '#1d3047', 100 => '#223a59', 200 => '#2c4a73', 300 => '#3f68a0'];

    private const DARK_TEXT = '#8ab8f2';

    public static function valid(string $hex): bool
    {
        return (bool) preg_match('/^#[0-9a-f]{6}$/i', $hex);
    }

    /** Contrast of white text on this colour (WCAG). */
    public static function contrastWithWhite(string $hex): float
    {
        return self::contrast($hex, '#ffffff');
    }

    /** WCAG contrast ratio of two colours (1 to 21). */
    public static function contrast(string $a, string $b): float
    {
        [$hi, $lo] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /**
     * Usable as the site colour: white text on it, and it as text on its own lightest tint (chips, light boxes),
     * are both easy to read.
     */
    public static function readable(string $hex): bool
    {
        return self::valid($hex)
            && self::contrastWithWhite($hex) >= self::MIN_CONTRAST
            && self::contrast($hex, self::palette($hex)['light'][50]) >= self::MIN_CONTRAST;
    }

    /**
     * @return array{light: array<int, string>, dark: array<int, string>, dark_text: string}
     */
    public static function palette(string $hex): array
    {
        $hex = strtolower($hex);
        [$baseL, $baseC, $baseH] = self::toOklch($hex);
        [$refL, $refC] = self::toOklch(self::LIGHT[700]);

        // How much more (or less) colourful the pick is than the blue; greys stay grey.
        $chromaRatio = $refC > 0 ? $baseC / $refC : 0.0;
        $make = function (string $refHex, bool $followLightness) use ($baseL, $baseH, $refL, $chromaRatio): string {
            [$l, $c] = self::toOklch($refHex);
            // Dark shades move with the pick's lightness; light tints keep the blue's (so they stay light).
            $l = $followLightness ? max(0.05, min(0.95, $l + ($baseL - $refL))) : $l;

            return self::fromOklch($l, $c * $chromaRatio, $baseH);
        };

        $light = [];
        foreach (self::LIGHT as $step => $ref) {
            $light[$step] = $step === 700 ? $hex : $make($ref, $step >= 600);
        }
        $dark = [];
        foreach (self::DARK as $step => $ref) {
            $dark[$step] = $make($ref, false);
        }

        return ['light' => $light, 'dark' => $dark, 'dark_text' => $make(self::DARK_TEXT, false)];
    }

    /**
     * The <style> that puts the chosen colour on the site, or '' for the iibsom blue (nothing to change).
     * Same selectors as resources/css/app.css, written after it, so they win.
     */
    public static function css(?string $hex): string
    {
        if ($hex === null || ! self::readable($hex) || strtolower($hex) === self::DEFAULT) {
            return '';
        }
        $p = self::palette($hex);
        $vars = fn (array $shades): string => implode('', array_map(fn ($step, $v) => "--color-brand-{$step}:{$v};", array_keys($shades), $shades));
        $lightTints = array_intersect_key($p['light'], array_flip([50, 100, 200, 300]));

        return ':root{'.$vars($p['light']).'}'
            .":root[data-theme='dark']{".$vars($p['dark']).'}'
            .":root[data-theme='dark'] :is(.text-brand-700,.text-brand-800,.text-brand-900,.prose-iibsom a){color:{$p['dark_text']};}"
            .":root[data-theme='dark'] :is(.bg-brand-600,.bg-brand-700,.bg-brand-800,.bg-brand-900,.bg-brand-950){".$vars($lightTints).'}';
    }

    /**
     * The same colour made darker, step by step, until it is usable (for "Try this one instead"); null when even
     * very dark it is not (never for real colours).
     */
    public static function suggest(string $hex): ?string
    {
        if (! self::valid($hex)) {
            return null;
        }
        if (self::readable($hex)) {
            return strtolower($hex);
        }
        [$l, $c, $h] = self::toOklch(strtolower($hex));
        for ($step = $l - 0.01; $step > 0.2; $step -= 0.01) {
            $try = self::fromOklch($step, $c, $h);
            if (self::readable($try)) {
                return $try;
            }
        }

        return null;
    }

    /** The colour the site uses now (the iibsom blue while the switch is OFF or the saved colour is not usable). */
    public static function current(): string
    {
        $hex = feature('site.custom_color') ? (string) setting('site.brand_color', self::DEFAULT) : self::DEFAULT;

        return self::readable($hex) ? strtolower($hex) : self::DEFAULT;
    }

    // ---- colour maths (sRGB <-> OKLab, Björn Ottosson) ----

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(fn ($c) => self::toLinear($c / 255), self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    private static function toLinear(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function fromLinear(float $c): float
    {
        return $c <= 0.0031308 ? 12.92 * $c : 1.055 * ($c ** (1 / 2.4)) - 0.055;
    }

    /** @return array{0: float, 1: float, 2: float} lightness 0–1, chroma, hue in degrees */
    private static function toOklch(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($c) => self::toLinear($c / 255), self::rgb($hex));
        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);
        $L = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $A = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $B = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        return [$L, sqrt($A * $A + $B * $B), fmod(rad2deg(atan2($B, $A)) + 360, 360)];
    }

    /** Back to #rrggbb; when the colour is outside what screens show, the chroma is lowered until it fits. */
    private static function fromOklch(float $L, float $C, float $H): string
    {
        for ($i = 0; $i < 40; $i++) {
            $rgb = self::oklchToLinearRgb($L, $C, $H);
            if (min($rgb) >= -0.0005 && max($rgb) <= 1.0005) {
                break;
            }
            $C *= 0.92;
        }
        $rgb = self::oklchToLinearRgb($L, $C, $H);

        return '#'.implode('', array_map(fn ($c) => str_pad(dechex((int) round(max(0, min(1, self::fromLinear(max(0.0, $c)))) * 255)), 2, '0', STR_PAD_LEFT), $rgb));
    }

    /** @return array{0: float, 1: float, 2: float} */
    private static function oklchToLinearRgb(float $L, float $C, float $H): array
    {
        $a = $C * cos(deg2rad($H));
        $b = $C * sin(deg2rad($H));
        $l = ($L + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($L - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($L - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960771 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }
}
