<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Activity;
use App\Support\Settings\SettingsManager;
use App\Support\Theme\SiteColors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Settings → Appearance → Website colour (PR 88, switch "site.custom_color"). One colour; the shades are made
 * from it (SiteColors). Only colours that keep text easy to read can be saved. Saving refreshes the page cache.
 */
class SiteColorController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->describe((string) setting('site.brand_color', SiteColors::DEFAULT)) + [
            'default' => SiteColors::DEFAULT,
            'enabled' => feature('site.custom_color'),
        ]);
    }

    /** Preview without saving: is it readable, and the shades it gives. */
    public function check(Request $request): JsonResponse
    {
        return response()->json($this->describe($this->colour($request)));
    }

    public function update(Request $request, SettingsManager $settings): JsonResponse
    {
        $color = $this->colour($request);
        if (! SiteColors::readable($color)) {
            $better = SiteColors::suggest($color);
            throw ValidationException::withMessages(['color' => 'This colour is too light: text on it would be hard to read.'.($better ? " Try {$better}." : '')]);
        }
        $old = (string) setting('site.brand_color', SiteColors::DEFAULT);
        $settings->set('site.brand_color', $color, $this->authUser($request)->id); // also refreshes the page cache
        Activity::log('settings.site_color', "Website colour changed from {$old} to {$color}", null, ['old' => $old, 'new' => $color]);

        return response()->json($this->describe($color));
    }

    private function colour(Request $request): string
    {
        return strtolower($request->validate(['color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/']])['color']);
    }

    /** @return array{color: string, readable: bool, contrast: float, suggestion: ?string, palette: array<int, string>} */
    private function describe(string $color): array
    {
        $color = SiteColors::valid($color) ? strtolower($color) : SiteColors::DEFAULT;

        return [
            'color' => $color,
            'readable' => SiteColors::readable($color),
            'contrast' => round(SiteColors::contrastWithWhite($color), 2),
            'suggestion' => SiteColors::suggest($color),
            'palette' => SiteColors::palette($color)['light'],
        ];
    }
}
