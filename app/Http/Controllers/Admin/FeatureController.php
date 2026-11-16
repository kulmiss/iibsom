<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Activity;
use App\Support\Ads\AdCounters;
use App\Support\Features\FeatureManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Feature switches: one per feature, grouped, with what changes when it is OFF. */
class FeatureController extends Controller
{
    public function index(FeatureManager $features): JsonResponse
    {
        $groups = $features->grouped();
        $all = collect($groups)->flatten(1);

        return response()->json([
            'summary' => ['on' => $all->where('enabled', true)->count(), 'off' => $all->where('enabled', false)->count()],
            'groups' => collect($groups)->map(fn ($items, $group) => [
                'group' => $group,
                'items' => collect($items)->map(fn ($f) => $f + ['changed' => $f['enabled'] !== $f['default'], 'effect_off' => $this->effectOff($f['key'])])->values(),
            ])->values(),
        ]);
    }

    public function update(Request $request, FeatureManager $features, string $key): JsonResponse
    {
        abort_unless($features->exists($key), 404);
        $on = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        $features->set($key, $on, $request->user()->id);
        if ($key === 'site.demo_data') {
            AdCounters::refreshAll();
        }
        Activity::log('feature.'.($on ? 'on' : 'off'), 'Switched “'.config("features.{$key}.label").'” '.($on ? 'ON' : 'OFF'), null, ['key' => $key]);

        return response()->json(['key' => $key, 'enabled' => $features->enabled($key)]);
    }

    public function reset(Request $request, FeatureManager $features, string $key): JsonResponse
    {
        abort_unless($features->exists($key), 404);
        $features->reset($key);
        Activity::log('feature.reset', 'Reset “'.config("features.{$key}.label").'” to default', null, ['key' => $key]);

        return response()->json(['key' => $key, 'enabled' => $features->enabled($key)]);
    }

    /** "Preview of what changes" in easy English. */
    private function effectOff(string $key): string
    {
        return match ($key) {
            'login.google' => 'Nobody can sign in with Google. The sign-in page and button disappear. Signed-in people stay signed in.',
            'ads.posting' => 'The Sell / Post ad buttons disappear and nobody can post new ads. Existing ads stay.',
            'ads.approval' => 'New ads go live at once, without a check by staff.',
            'ads.expiry' => 'Ads stop ending after the set number of days.',
            'contact.whatsapp' => 'The WhatsApp button is removed from every ad page.',
            'contact.call' => 'The Call button is removed from every ad page.',
            'site.language_switch' => 'Visitors cannot change the language.',
            'site.demo_data' => 'Demo ads, users and reports are hidden everywhere (site, search, admin lists, charts).',
            default => 'Hidden everywhere: pages, menus, admin, API, sitemap. Data is kept and comes back when switched ON.',
        };
    }
}
