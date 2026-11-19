<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Support\Admin\Activity;
use App\Support\Ads\AdCounters;
use App\Support\Features\FeatureManager;
use App\Support\Search\SearchTerms;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * First-time setup wizard for the Super Admin (5 short steps). Everything is
 * saved at the end in one go, so a half-finished wizard changes nothing.
 */
class SetupController extends Controller
{
    public function show(GeneralSettings $general): JsonResponse
    {
        return response()->json([
            'settings' => $general->values(),
            'cities' => City::query()->with('region:id,name')->orderBy('sort')->get()
                ->map(fn (City $c) => ['id' => $c->id, 'name' => $c->label, 'region' => $c->region->label, 'enabled' => $c->enabled])->values(),
            'demo_data' => feature('site.demo_data'),
            'completed' => self::completed(),
        ]);
    }

    public function store(Request $request, GeneralSettings $general, SettingsManager $settings, FeatureManager $features): JsonResponse
    {
        $data = $request->validate(GeneralSettings::rules() + [
            'cities' => ['required', 'array', 'min:1', 'max:500'],
            'cities.*' => ['integer', 'distinct', Rule::exists('cities', 'id')],
            'demo_data' => ['required', 'boolean'],
        ], GeneralSettings::messages() + [
            'cities.required' => 'Choose at least one city.',
            'cities.min' => 'Choose at least one city.',
        ]);
        if ($problems = GeneralSettings::socialProblems($data)) {
            throw ValidationException::withMessages($problems);
        }
        $userId = $this->authUser($request)->id;

        DB::transaction(function () use ($data, $general, $settings, $features, $userId) {
            $general->save($data, $userId);

            $on = array_map('intval', $data['cities']);
            foreach (City::query()->with('region')->get() as $city) {
                $city->enabled = in_array($city->id, $on, true);
                $city->save();
                // A city shows only when its region is ON too.
                if ($city->enabled && ! $city->region->enabled) {
                    $city->region->enabled = true;
                    $city->region->save();
                }
            }

            $features->set('site.demo_data', (bool) $data['demo_data'], $userId);
            $settings->set('setup.completed_at', now()->toIso8601String(), $userId);
        });

        // Demo ads may have been shown or hidden: live-ad totals and search words follow.
        AdCounters::refreshAll();
        defer(fn () => SearchTerms::rebuild());

        Activity::log('setup.finished', 'Finished the first-time setup', null, ['cities_on' => count($data['cities'])]);

        return response()->json(['completed' => true]);
    }

    /** "Later": the wizard stops opening by itself. It can still be run from Settings. */
    public function skip(Request $request, SettingsManager $settings): JsonResponse
    {
        $settings->set('setup.completed_at', 'skipped '.now()->toIso8601String(), $this->authUser($request)->id);
        Activity::log('setup.skipped', 'Skipped the first-time setup');

        return response()->json(['completed' => true]);
    }

    public static function completed(): bool
    {
        return setting('setup.completed_at') !== '';
    }
}
