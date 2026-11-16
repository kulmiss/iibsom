<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdResource;
use App\Models\Category;
use App\Search\AdSearch;
use App\Search\DatabaseAdSearch;
use App\Search\SearchRequest;
use App\Support\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Ads: the same search and the same "only live ads" rule as the website. */
class AdApiController extends Controller
{
    public function index(Request $request, AdSearch $search, Catalog $catalog): AnonymousResourceCollection
    {
        $request->validate([
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

        // Unknown or OFF category/city = no results (id 0 never exists).
        $categoryIds = null;
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->string('category')->toString())->first();
            $categoryIds = ! $category || ! $category->isLive() ? [0]
                : ($category->parent_id === null ? SearchRequest::ids($category->children()->where('enabled', true)->pluck('id')) : [$category->id]);
        }
        $cityId = null;
        if ($request->filled('city')) {
            $city = collect($catalog->cities())->firstWhere('slug', $request->string('city')->toString());
            $cityId = is_array($city) ? $city['id'] : 0;
        }

        $results = $search->search(SearchRequest::basic($request) + [
            'category_ids' => $categoryIds,
            'city_id' => $cityId,
        ], $request->integer('per_page', 24), max(1, $request->integer('page', 1)))->withQueryString();

        return AdResource::collection($results);
    }

    public function show(int $id): JsonResponse
    {
        $ad = DatabaseAdSearch::publicQuery()
            ->with(['category', 'city', 'area', 'cover', 'user', 'photos' => fn ($q) => $q->where('status', 'ready')])
            ->find($id);

        abort_if($ad === null, 404);

        $resource = new AdResource($ad);
        $resource->detail = true;

        return $resource->response()->header('Cache-Control', 'public, max-age=60');
    }
}
