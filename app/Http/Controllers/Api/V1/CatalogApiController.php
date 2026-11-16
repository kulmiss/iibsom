<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Catalog;
use Illuminate\Http\JsonResponse;

/** Live categories (with sub categories) and live cities. */
class CatalogApiController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function categories(): JsonResponse
    {
        $rows = collect($this->catalog->categories())->map(fn (array $c) => [
            'id' => $c['id'],
            'slug' => $c['slug'],
            'name' => translated($c['name']),
            'icon' => $c['icon'],
            'ads_count' => $c['ads_count'],
            'children' => collect($c['children'])->map(fn (array $s) => [
                'id' => $s['id'], 'slug' => $s['slug'], 'name' => translated($s['name']), 'icon' => $s['icon'], 'ads_count' => $s['ads_count'],
            ])->values(),
        ])->values();

        return response()->json(['data' => $rows])->header('Cache-Control', 'public, max-age=300');
    }

    public function cities(): JsonResponse
    {
        $rows = collect($this->catalog->cities())->map(fn (array $c) => [
            'id' => $c['id'], 'slug' => $c['slug'], 'name' => translated($c['name']), 'ads_count' => $c['ads_count'],
        ])->values();

        return response()->json(['data' => $rows])->header('Cache-Control', 'public, max-age=300');
    }
}
