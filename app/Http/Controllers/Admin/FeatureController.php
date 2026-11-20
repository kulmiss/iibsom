<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Activity;
use App\Support\Ads\AdCounters;
use App\Support\Features\FeatureManager;
use App\Support\Search\SearchTerms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Feature switches: one per feature, grouped, with what changes when it is OFF. */
class FeatureController extends Controller
{
    /**
     * Sections of the page (PR 99): by what a switch changes, not by when it was built ("Site" had 71 switches).
     * Key prefix → section; Phase 2 and Phase 3 keep their own sections (built only when the owner says).
     */
    public const SECTIONS = [
        'Home page' => ['home'],
        'Ads and ad pages' => ['ads'],
        'Posting an ad' => ['post'],
        'Search and categories' => ['search', 'catalog', 'lists', 'seo', 'recommendations'],
        'Sign-in, accounts and sellers' => ['login', 'account', 'sellers', 'favourites', 'saved_searches', 'notifications', 'ratings'],
        'Chat and contact' => ['chat', 'contact', 'share'],
        'Safety and reports' => ['safety', 'reports', 'photos'],
        'Help pages' => ['help'],
        'Whole site' => ['site', 'pwa'],
        'Admin, speed and system' => ['admin', 'watchdog', 'performance', 'api'],
    ];

    public const PHASES = ['Phase 2' => 'Phase 2 (build when the owner says)', 'Phase 3' => 'Phase 3 (build when the owner says)'];

    public function index(FeatureManager $features): JsonResponse
    {
        $groups = $features->grouped();
        $all = collect($groups)->flatten(1);
        $order = array_merge(array_keys(self::SECTIONS), ['Other'], array_values(self::PHASES));

        $sections = [];
        foreach ($groups as $group => $items) {
            foreach ($items as $f) {
                $sections[self::section($f['key'], (string) $group)][] = $f + ['changed' => $f['enabled'] !== $f['default'], 'effect_off' => $this->effectOff($f['key'])];
            }
        }
        uksort($sections, fn ($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));

        return response()->json([
            'summary' => ['on' => $all->where('enabled', true)->count(), 'off' => $all->where('enabled', false)->count(), 'changed' => $all->filter(fn ($f) => $f['enabled'] !== $f['default'])->count()],
            'groups' => collect($sections)->map(fn ($items, $group) => ['group' => $group, 'items' => $items])->values(),
        ]);
    }

    public static function section(string $key, string $group): string
    {
        if (isset(self::PHASES[$group])) {
            return self::PHASES[$group];
        }
        $prefix = explode('.', $key, 2)[0];
        foreach (self::SECTIONS as $section => $prefixes) {
            if (in_array($prefix, $prefixes, true)) {
                return $section;
            }
        }

        return 'Other';
    }

    public function update(Request $request, FeatureManager $features, string $key): JsonResponse
    {
        abort_unless($features->exists($key), 404);
        $on = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        $features->set($key, $on, $this->authUser($request)->id);
        $this->afterChange($key);
        Activity::log('feature.'.($on ? 'on' : 'off'), 'Switched “'.$this->label($key).'” '.($on ? 'ON' : 'OFF'), null, ['key' => $key]);

        return response()->json(['key' => $key, 'enabled' => $features->enabled($key)]);
    }

    public function reset(Request $request, FeatureManager $features, string $key): JsonResponse
    {
        abort_unless($features->exists($key), 404);
        $features->reset($key);
        $this->afterChange($key);
        Activity::log('feature.reset', 'Reset “'.$this->label($key).'” to default', null, ['key' => $key]);

        return response()->json(['key' => $key, 'enabled' => $features->enabled($key)]);
    }

    /**
     * The switch's name. Keys have dots ("site.demo_data"), so config("features.{$key}.label") would read the
     * wrong place and give nothing (the activity log said "Switched “” ON" for those; fixed in PR 86).
     */
    private function label(string $key): string
    {
        return (string) (config('features')[$key]['label'] ?? $key);
    }

    /** Showing or hiding demo ads changes the live-ad totals and the words offered in search suggestions. */
    private function afterChange(string $key): void
    {
        if ($key === 'site.demo_data') {
            AdCounters::refreshAll();
            defer(fn () => SearchTerms::rebuild()); // after the answer is sent (a few seconds on big sites)
        }
    }

    /** "Preview of what changes" in easy English. */
    private function effectOff(string $key): string
    {
        return match ($key) {
            'login.google' => 'Nobody can sign in with Google. The Google button disappears. Signed-in people stay signed in.',
            'login.email' => 'Nobody can sign up or sign in with email and password. Signed-in people stay signed in.',
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
