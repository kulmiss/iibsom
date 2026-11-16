<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdResource;
use App\Models\Category;
use App\Search\AdSearch;
use App\Search\DatabaseAdSearch;
use App\Support\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Ads: the same search and the same "only live ads" rule as the website. */
class AdApiController extends Controller
{
    public function index(Request $request, AdSearch $search, Catalog $catalog): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
            'condition' => ['nullable', 'in:new,used'],
            'sort' => ['nullable', 'in:newest,cheapest,expensive'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $categoryIds = null;
        if (! empty($data['category'])) {
            $category = Category::where('slug', $data['category'])->first();
            $categoryIds = ! $category || ! $category->isLive() ? [0]
                : ($category->parent_id === null ? $category->children()->where('enabled', true)->pluck('id')->all() : [$category->id]);
        }
        $cityId = null;
        if (! empty($data['city'])) {
            $cityId = collect($catalog->cities())->firstWhere('slug', $data['city'])['id'] ?? 0;
        }

        $results = $search->search([
            'q' => $data['q'] ?? null,
            'category_ids' => $categoryIds,
            'city_id' => $cityId,
            'min' => $data['min'] ?? null,
            'max' => $data['max'] ?? null,
            'condition' => $data['condition'] ?? null,
            'sort' => $data['sort'] ?? 'newest',
        ], (int) ($data['per_page'] ?? 24), (int) ($data['page'] ?? 1))->withQueryString();

        return AdResource::collection($results);
    }

    public function show(int $id): JsonResponse
    {
        $ad = DatabaseAdSearch::publicQuery()
            ->with(['category', 'city', 'area', 'cover', 'user', 'photos' => fn ($q) => $q->where('status', 'ready')])
            ->find($id);

        abort_unless($ad, 404);

        $resource = new AdResource($ad);
        $resource->detail = true;

        return $resource->response()->header('Cache-Control', 'public, max-age=60');
    }
}
