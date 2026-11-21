<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\City;
use App\Models\Region;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Regions → cities → areas, each with an on/off switch. */
class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $regions = Region::query()->orderBy('sort')->with(['cities' => fn ($q) => $q->withCount(['areas', 'areas as areas_review' => fn ($a) => $a->where('needs_review', true)])])->get();

        return response()->json($regions->map(fn (Region $r) => [
            'id' => $r->id, 'name' => $r->label, 'enabled' => $r->enabled,
            'cities' => $r->cities->map(fn (City $c) => $this->cityRow($c)),
        ]));
    }

    public function updateRegion(Request $request, Region $region): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $region->update($data);
        Activity::log('region.'.($data['enabled'] ? 'on' : 'off'), "Region {$region->label} turned ".($data['enabled'] ? 'ON' : 'OFF'), $region);

        return response()->json(['id' => $region->id, 'enabled' => $region->enabled]);
    }

    public function storeCity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'name_en' => ['required', 'string', 'min:2', 'max:60'],
            'name_so' => ['nullable', 'string', 'max:60'],
        ]);
        $slug = Str::slug($data['name_en']);
        abort_if(City::where('slug', $slug)->exists(), 422, 'A city with this name already exists.');

        $city = new City(['region_id' => $data['region_id'], 'name' => array_filter(['en' => strip_tags($data['name_en']), 'so' => isset($data['name_so']) ? strip_tags($data['name_so']) : null])]);
        $city->slug = $slug;
        $city->enabled = false; // new cities start OFF
        $city->sort = max(0, (int) City::max('sort') + 1);
        $city->save();
        Activity::log('city.created', "Added city {$city->label} (OFF)", $city);

        return response()->json($this->cityRow($city->loadCount('areas')), 201);
    }

    public function updateCity(Request $request, City $city): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'popular' => ['sometimes', 'boolean'],
            'name_en' => ['sometimes', 'string', 'min:2', 'max:60'],
            'name_so' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);
        if (isset($data['name_en']) || array_key_exists('name_so', $data)) {
            $city->name = $this->names($data, $city->name);
        }
        foreach (['enabled', 'popular'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $city->{$flag} = $data[$flag];
            }
        }
        $city->save();
        Activity::log('city.updated', "Changed city {$city->label}".(array_key_exists('enabled', $data) ? ' → '.($city->enabled ? 'ON' : 'OFF') : ''), $city);

        return response()->json($this->cityRow($city->loadCount('areas')));
    }

    public function areas(City $city): JsonResponse
    {
        return response()->json($city->areas()->withCount('ads')->get()->map(fn (Area $a) => $this->areaRow($a)));
    }

    public function storeArea(Request $request, City $city): JsonResponse
    {
        $data = $request->validate(['name_en' => ['required', 'string', 'min:2', 'max:60'], 'name_so' => ['nullable', 'string', 'max:60']]);
        $slug = Str::slug($data['name_en']);
        abort_if($city->areas()->where('slug', $slug)->exists(), 422, 'This area already exists.');

        $area = Area::create(['city_id' => $city->id, 'name' => array_filter(['en' => strip_tags($data['name_en']), 'so' => isset($data['name_so']) ? strip_tags($data['name_so']) : null]), 'slug' => $slug, 'sort' => max(0, (int) $city->areas()->max('sort') + 1)]);
        Activity::log('area.created', "Added area {$area->label} to {$city->label}", $area);

        return response()->json($this->areaRow($area->refresh()->loadCount('ads')), 201); // refresh: the database defaults (ON, checked)
    }

    public function updateArea(Request $request, Area $area): JsonResponse
    {
        $data = $request->validate(['enabled' => ['sometimes', 'boolean'], 'name_en' => ['sometimes', 'string', 'min:2', 'max:60'], 'name_so' => ['sometimes', 'nullable', 'string', 'max:60'], 'reviewed' => ['sometimes', 'accepted']]);
        if (isset($data['name_en']) || array_key_exists('name_so', $data)) {
            $area->name = $this->names($data, $area->name);
            $area->needs_review = false; // the names were looked at and saved (PR 111)
        }
        if (isset($data['reviewed'])) {
            $area->needs_review = false; // "Names are right" (PR 111)
        }
        if (array_key_exists('enabled', $data)) {
            $area->enabled = $data['enabled'];
        }
        $area->save();
        Activity::log('area.updated', "Changed area {$area->label}", $area);

        return response()->json($this->areaRow($area->loadCount('ads')));
    }

    /** Move a district one place up or down in its city's list (PR 111). */
    public function moveArea(Request $request, Area $area): JsonResponse
    {
        $up = $request->validate(['direction' => ['required', 'in:up,down']])['direction'] === 'up';
        DB::transaction(function () use ($area, $up) {
            $list = Area::where('city_id', $area->city_id)->orderBy('sort')->orderBy('id')->lockForUpdate()->get()->values();
            $i = $list->search(fn (Area $a) => $a->id === $area->id);
            $j = $up ? $i - 1 : $i + 1;
            if ($i === false || ! isset($list[$j])) {
                return; // already first / last
            }
            $order = $list->all();
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            foreach ($order as $sort => $a) {
                if ($a->sort !== $sort) {
                    $a->forceFill(['sort' => $sort])->save();
                }
            }
        });
        Activity::log('area.moved', "Moved area {$area->label} ".($up ? 'up' : 'down'), $area);

        return response()->json($area->city->areas()->withCount('ads')->get()->map(fn (Area $a) => $this->areaRow($a)));
    }

    /** Delete a district nobody used yet; one with ads can only be turned off (their ads keep it). */
    public function destroyArea(Area $area): JsonResponse
    {
        abort_if($area->ads()->withTrashed()->exists(), 422, 'Ads use this area. Turn it off instead: those ads keep it.');
        $label = $area->label;
        $area->delete();
        Activity::log('area.deleted', "Deleted area {$label}", null);

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function areaRow(Area $a): array
    {
        return [
            'id' => $a->id, 'name_en' => $a->name['en'] ?? '', 'name_so' => $a->name['so'] ?? null, 'enabled' => $a->enabled,
            'needs_review' => $a->needs_review, 'ads_count' => (int) ($a->ads_count ?? 0),
        ];
    }

    /**
     * New names from the form. A Somali name sent as empty or null is removed;
     * one not sent at all is kept.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $current
     * @return array<string, string>
     */
    private function names(array $data, array $current): array
    {
        $en = is_string($data['name_en'] ?? null) ? strip_tags($data['name_en']) : ($current['en'] ?? '');
        $so = array_key_exists('name_so', $data)
            ? (is_string($data['name_so']) ? strip_tags($data['name_so']) : '')
            : ($current['so'] ?? '');

        return array_filter(['en' => $en, 'so' => $so], fn (string $v) => $v !== '');
    }

    /** @return array<string, mixed> */
    private function cityRow(City $c): array
    {
        return [
            'id' => $c->id, 'region_id' => $c->region_id, 'name_en' => $c->name['en'] ?? '', 'name_so' => $c->name['so'] ?? null,
            'slug' => $c->slug, 'enabled' => $c->enabled, 'popular' => $c->popular, 'ads_count' => $c->ads_count, 'areas_count' => $c->areas_count ?? 0, 'areas_review' => (int) ($c->areas_review ?? 0),
        ];
    }
}
