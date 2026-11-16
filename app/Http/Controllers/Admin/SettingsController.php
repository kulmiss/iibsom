<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Activity;
use App\Support\Settings\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Settings → General. */
class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->values());
    }

    public function update(Request $request, SettingsManager $settings): JsonResponse
    {
        $data = $request->validate([
            'ads_expiry_days' => ['required', 'integer', 'min:7', 'max:365'],
            'ads_max_per_day' => ['required', 'integer', 'min:1', 'max:100'],
            'ads_max_photos' => ['required', 'integer', 'min:1', 'max:20'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => [Rule::in(array_keys(config('iibsom.locales')))],
        ]);

        $before = $this->values();
        $languages = array_values(array_unique([config('iibsom.default_locale'), ...$data['languages']]));

        $settings->set('ads.expiry_days', (int) $data['ads_expiry_days'], $request->user()->id);
        $settings->set('ads.max_per_day', (int) $data['ads_max_per_day'], $request->user()->id);
        $settings->set('ads.max_photos', (int) $data['ads_max_photos'], $request->user()->id);
        $settings->set('languages.enabled', $languages, $request->user()->id);

        Activity::log('settings.general', 'Changed general settings', null, ['before' => $before]);

        return response()->json($this->values());
    }

    private function values(): array
    {
        return [
            'ads_expiry_days' => setting('ads.expiry_days'),
            'ads_max_per_day' => setting('ads.max_per_day'),
            'ads_max_photos' => setting('ads.max_photos'),
            'languages' => setting('languages.enabled'),
            'available_languages' => collect(config('iibsom.locales'))->map(fn ($l, $code) => ['code' => $code, 'name' => $l['name'], 'default' => $code === config('iibsom.default_locale')])->values(),
            'currency' => setting('currency'),
        ];
    }
}
