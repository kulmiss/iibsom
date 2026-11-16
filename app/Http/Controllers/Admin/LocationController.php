<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\City;
use App\Models\Region;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Regions → cities → areas, each with an on/off switch. */
class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $regions = Region::query()->orderBy('sort')->with(['cities' => fn ($q) => $q->withCount('areas')])->get();

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
        $city->sort = (int) City::max('sort') + 1;
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
            $city->name = array_filter(['en' => strip_tags($data['name_en'] ?? $city->name['en']), 'so' => isset($data['name_so']) ? strip_tags($data['name_so']) : ($city->name['so'] ?? null)]);
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
        return response()->json($city->areas()->get()->map(fn (Area $a) => ['id' => $a->id, 'name_en' => $a->name['en'] ?? '', 'name_so' => $a->name['so'] ?? null, 'enabled' => $a->enabled]));
    }

    public function storeArea(Request $request, City $city): JsonResponse
    {
        $data = $request->validate(['name_en' => ['required', 'string', 'min:2', 'max:60'], 'name_so' => ['nullable', 'string', 'max:60']]);
        $slug = Str::slug($data['name_en']);
        abort_if($city->areas()->where('slug', $slug)->exists(), 422, 'This area already exists.');

        $area = Area::create(['city_id' => $city->id, 'name' => array_filter(['en' => strip_tags($data['name_en']), 'so' => isset($data['name_so']) ? strip_tags($data['name_so']) : null]), 'slug' => $slug, 'sort' => (int) $city->areas()->max('sort') + 1]);
        Activity::log('area.created', "Added area {$area->label} to {$city->label}", $area);

        return response()->json(['id' => $area->id, 'name_en' => $area->name['en'], 'name_so' => $area->name['so'] ?? null, 'enabled' => $area->enabled], 201);
    }

    public function updateArea(Request $request, Area $area): JsonResponse
    {
        $data = $request->validate(['enabled' => ['sometimes', 'boolean'], 'name_en' => ['sometimes', 'string', 'min:2', 'max:60'], 'name_so' => ['sometimes', 'nullable', 'string', 'max:60']]);
        if (isset($data['name_en']) || array_key_exists('name_so', $data)) {
            $area->name = array_filter(['en' => strip_tags($data['name_en'] ?? $area->name['en']), 'so' => isset($data['name_so']) ? strip_tags($data['name_so']) : ($area->name['so'] ?? null)]);
        }
        if (array_key_exists('enabled', $data)) {
            $area->enabled = $data['enabled'];
        }
        $area->save();
        Activity::log('area.updated', "Changed area {$area->label}", $area);

        return response()->json(['id' => $area->id, 'name_en' => $area->name['en'], 'name_so' => $area->name['so'] ?? null, 'enabled' => $area->enabled]);
    }

    private function cityRow(City $c): array
    {
        return [
            'id' => $c->id, 'region_id' => $c->region_id, 'name_en' => $c->name['en'] ?? '', 'name_so' => $c->name['so'] ?? null,
            'slug' => $c->slug, 'enabled' => $c->enabled, 'popular' => $c->popular, 'ads_count' => $c->ads_count, 'areas_count' => $c->areas_count ?? 0,
        ];
    }
}
