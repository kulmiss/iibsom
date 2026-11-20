<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Features\FeatureManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Admin → Home page (PR 86): the parts of the customer home page, top to bottom (config/home.php),
 * each with its own switch. Turning a part on or off uses the normal feature-switch endpoint
 * (PUT /features/{key}), so the activity log and the page cache work as for every switch.
 */
class HomePageController extends Controller
{
    private const AREAS = ['top' => 'Top (blue box)', 'main' => 'Middle', 'lower' => 'Bottom'];

    public function index(FeatureManager $features): JsonResponse
    {
        $all = (array) config('features'); // keys have dots, so not config("features.{$key}")
        $label = fn (string $key): string => (string) ($all[$key]['label'] ?? $key);
        $need = fn (string $key): array => ['key' => $key, 'label' => $label($key), 'enabled' => $features->enabled($key)];

        $sections = [];
        foreach ((array) config('home.sections') as $i => $s) {
            $key = $s['key'] ?? null;
            $needs = array_map($need, $s['needs'] ?? []);
            $needsAny = array_map($need, $s['needs_any'] ?? []);
            // What else must be ON before this part can show (empty = nothing).
            $waiting = array_values(array_filter($needs, fn ($n) => ! $n['enabled']));
            if ($needsAny !== [] && ! in_array(true, array_column($needsAny, 'enabled'), true)) {
                $waiting[] = ['key' => $needsAny[0]['key'], 'label' => implode(' or ', array_column($needsAny, 'label')), 'enabled' => false];
            }

            $sections[] = [
                'position' => $i + 1,
                'area' => self::AREAS[$s['area']] ?? $s['area'],
                'fixed' => (bool) ($s['fixed'] ?? false),
                'key' => $key,
                'label' => $key ? Str::ucfirst((string) preg_replace('/^Home: /', '', $label($key))) : $s['label'],
                'description' => $key ? (string) ($all[$key]['description'] ?? '') : $s['description'],
                'note' => $s['note'] ?? null,
                'enabled' => $key ? $features->enabled($key) : true,
                'default' => $key ? (bool) ($all[$key]['default'] ?? true) : true,
                'waiting_for' => $key ? $waiting : [],
            ];
        }

        return response()->json(['sections' => $sections, 'home_url' => url('/')]);
    }
}
