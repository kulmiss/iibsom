<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\City;
use App\Models\Region;
use Database\Seeders\Concerns\TranslatesNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Somalia locations (decision #2, #7, #11): only Mogadishu is ON at launch.
 * Safe to run again: it updates names but never changes on/off switches.
 */
class LocationSeeder extends Seeder
{
    use TranslatesNames;

    /** region => [city => [areas]] ; Mogadishu areas = its districts */
    private const DATA = [
        'Banadir' => [
            'Mogadishu' => [
                'Abdiaziz', 'Bondhere', 'Daynile', 'Dharkenley', 'Garasbaley', 'Hamar Jajab', 'Hamar Weyne',
                'Heliwa', 'Hodan', 'Howlwadaag', 'Kaaraan', 'Kaxda', 'Shangani', 'Shibis', 'Waberi',
                'Wadajir', 'Wardhigley', 'Yaqshid',
            ],
        ],
        'Lower Shabelle' => ['Afgooye' => [], 'Marka' => []],
        'Middle Shabelle' => ['Jowhar' => [], 'Balcad' => []],
        'Hiiraan' => ['Beledweyne' => []],
        'Galgaduud' => ['Dhusamareb' => [], 'Guriceel' => []],
        'Mudug' => ['Galkayo' => [], 'Hobyo' => []],
        'Nugaal' => ['Garowe' => []],
        'Bari' => ['Bosaso' => [], 'Qardho' => []],
        'Bay' => ['Baidoa' => []],
        'Bakool' => ['Hudur' => []],
        'Gedo' => ['Garbahaarey' => [], 'Luuq' => []],
        'Lower Juba' => ['Kismayo' => []],
        'Middle Juba' => ['Jilib' => []],
    ];

    private const ON_AT_LAUNCH = ['mogadishu'];

    public function run(): void
    {
        $regionSort = 0;
        $citySort = 0;

        foreach (self::DATA as $regionName => $cities) {
            $region = Region::firstOrNew(['slug' => Str::slug($regionName)]);
            $region->fill(['name' => $this->names($region->exists ? $region->name : null, $regionName), 'sort' => $regionSort++]);
            if (! $region->exists) {
                $region->enabled = true; // regions ON; each city has its own switch
            }
            $region->save();

            foreach ($cities as $cityName => $areas) {
                $slug = Str::slug($cityName);
                $city = City::firstOrNew(['slug' => $slug]);
                $city->fill(['region_id' => $region->id, 'name' => $this->names($city->exists ? $city->name : null, $cityName), 'sort' => $citySort++]);
                if (! $city->exists) {
                    $city->enabled = in_array($slug, self::ON_AT_LAUNCH, true);
                    $city->popular = $city->enabled;
                }
                $city->save();

                foreach ($areas as $i => $areaName) {
                    $area = Area::firstOrNew(['city_id' => $city->id, 'slug' => Str::slug($areaName)]);
                    $area->fill(['name' => $this->names($area->exists ? $area->name : null, $areaName), 'sort' => $i])->save();
                }
            }
        }
    }
}
