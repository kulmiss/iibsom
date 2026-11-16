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
            'languages.*' => [Rule::in(array_keys(config()->array('iibsom.locales')))],
            'bad_words' => ['nullable', 'string', 'max:20000'],
        ]);

        $before = $this->values();
        $languages = array_values(array_unique([config('iibsom.default_locale'), ...$data['languages']]));

        $settings->set('ads.expiry_days', (int) $data['ads_expiry_days'], $this->authUser($request)->id);
        $settings->set('ads.max_per_day', (int) $data['ads_max_per_day'], $this->authUser($request)->id);
        $settings->set('ads.max_photos', (int) $data['ads_max_photos'], $this->authUser($request)->id);
        $settings->set('languages.enabled', $languages, $this->authUser($request)->id);
        if ($request->has('bad_words')) {
            $words = collect(preg_split('/\r?\n/', (string) ($data['bad_words'] ?? '')) ?: [])
                ->map(fn ($w) => mb_strtolower(trim($w)))->filter()->unique()->take(1000)->values()->all();
            $settings->set('safety.bad_words', $words, $this->authUser($request)->id);
        }

        Activity::log('settings.general', 'Changed general settings', null, ['before' => $before]);

        return response()->json($this->values());
    }

    /** @return array<string, mixed> */
    private function values(): array
    {
        return [
            'ads_expiry_days' => setting('ads.expiry_days'),
            'ads_max_per_day' => setting('ads.max_per_day'),
            'ads_max_photos' => setting('ads.max_photos'),
            'languages' => setting('languages.enabled'),
            'available_languages' => collect(config()->array('iibsom.locales'))->map(fn ($l, $code) => ['code' => $code, 'name' => $l['name'], 'default' => $code === config('iibsom.default_locale')])->values(),
            'currency' => setting('currency'),
            'bad_words' => implode("\n", setting('safety.bad_words') ?? []),
        ];
    }
}
