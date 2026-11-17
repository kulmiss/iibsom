<?php

namespace App\Support\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Similar ads sell for $X – $Y": the middle half of the prices of similar
 * live or sold ads from the last 6 months (switch "price_suggestions").
 *
 * Tries the closest match first (brand + model), then brand, then the
 * whole sub category. Needs at least MIN_ADS ads, so one seller's price
 * can never be worked out from the answer.
 */
class PriceHint
{
    public const MIN_ADS = 5;

    public const DAYS = 180;

    public const MAX_ROWS = 2000;

    /**
     * @param  array<string, mixed>  $fields  the form's custom fields (brand, model ...)
     * @return array{low: int, high: int, middle: int, count: int, scope: string}|null
     */
    public static function for(Category $category, ?string $condition, array $fields, ?int $exceptAdId = null): ?array
    {
        $brand = is_string($fields['brand'] ?? null) ? Str::lower(trim($fields['brand'])) : '';
        $model = is_string($fields['model'] ?? null) ? Str::lower(Str::squish($fields['model'])) : '';
        $condition = in_array($condition, ['new', 'used'], true) && $category->has_condition ? $condition : null;

        $key = 'price-hint:'.md5(implode('|', [$category->id, $condition, $brand, mb_substr($model, 0, 60), $exceptAdId, (int) feature('site.demo_data')]));

        return Cache::remember($key, 3600, function () use ($category, $condition, $brand, $model, $exceptAdId) {
            $tries = [];
            if ($brand !== '' && $model !== '') {
                $tries[] = ['brand_model', $brand, $model];
            }
            if ($brand !== '') {
                $tries[] = ['brand', $brand, ''];
            }
            $tries[] = ['category', '', ''];

            foreach ($tries as [$scope, $b, $m]) {
                $prices = array_map('intval', self::query($category, $condition, $b, $m, $exceptAdId)
                    ->latest('published_at')->limit(self::MAX_ROWS)->pluck('price')->all());
                sort($prices);

                if (count($prices) >= self::MIN_ADS) {
                    return [
                        'low' => self::nice(self::percentile($prices, 0.25)),
                        'high' => self::nice(self::percentile($prices, 0.75)),
                        'middle' => self::nice(self::percentile($prices, 0.5)),
                        'count' => count($prices),
                        'scope' => $scope,
                    ];
                }
            }

            return null;
        });
    }

    /** @return Builder<Ad> */
    private static function query(Category $category, ?string $condition, string $brand, string $model, ?int $exceptAdId): Builder
    {
        return Ad::query()
            ->where('category_id', $category->id)
            ->whereIn('status', [AdStatus::Live, AdStatus::Sold])
            ->whereNotNull('price')->where('price', '>', 0)
            ->where('published_at', '>=', now()->subDays(self::DAYS))
            ->when(! feature('site.demo_data'), fn ($q) => $q->where('is_demo', false))
            ->when($condition !== null, fn ($q) => $q->where('condition', $condition))
            ->when($brand !== '', fn ($q) => $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(fields, '$.brand'))) = ?", [$brand]))
            ->when($model !== '', fn ($q) => $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(fields, '$.model'))) = ?", [$model]))
            ->when($exceptAdId !== null, fn ($q) => $q->whereKeyNot($exceptAdId));
    }

    /** @param  list<int>  $sorted  prices, smallest first (not empty) */
    public static function percentile(array $sorted, float $q): float
    {
        $pos = (count($sorted) - 1) * $q;
        $lower = (int) floor($pos);
        $upper = (int) ceil($pos);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($pos - $lower);
    }

    /** Round to a price people say: 87 → 87, 643 → 640, 5 830 → 5 800, 123 456 → 120 000. */
    public static function nice(float $price): int
    {
        if ($price < 100) {
            return (int) round($price);
        }
        $step = 10 ** ((int) floor(log10($price)) - 1);

        return (int) (round($price / $step) * $step);
    }
}
