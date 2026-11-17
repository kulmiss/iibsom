<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Ads\PriceHint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The "price guide" box under the price on the post / edit form (switch "price_suggestions"). */
class PriceHintController extends Controller
{
    public function show(Request $request, Category $category, PostAdController $post): JsonResponse
    {
        $post->ensurePostable($category);
        $data = $request->validate([
            'condition' => ['nullable', 'in:new,used'],
            'fields' => ['nullable', 'array'],
            'fields.brand' => ['nullable', 'string', 'max:100'],
            'fields.model' => ['nullable', 'string', 'max:100'],
            'except' => ['nullable', 'integer', 'min:1'],
        ]);

        $hint = PriceHint::for($category, $data['condition'] ?? null, $data['fields'] ?? [], isset($data['except']) ? (int) $data['except'] : null);
        if ($hint === null) {
            return response()->json(['hint' => null]);
        }

        $brandField = $category->allFields()->firstWhere('key', 'brand');
        $brand = $brandField ? ($brandField->optionList()[(string) ($data['fields']['brand'] ?? '')] ?? '') : '';
        $what = match ($hint['scope']) {
            'brand_model' => trim($brand.' '.mb_substr((string) ($data['fields']['model'] ?? ''), 0, 40)),
            'brand' => $brand,
            default => $category->label,
        };

        return response()->json(['hint' => $hint + [
            'range' => __('ads.price_hint.range', ['what' => $what ?: $category->label, 'low' => number_format($hint['low']), 'high' => number_format($hint['high'])]),
            'detail' => __('ads.price_hint.detail', ['middle' => number_format($hint['middle']), 'count' => $hint['count']]),
        ]]);
    }
}
