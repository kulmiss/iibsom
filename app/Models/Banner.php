<?php

namespace App\Models;

use App\Support\Cache\PageCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Home-slider banners (Phase 1) and banner ads in paid slots (switch "banner_ads").
 *
 * @property array<string, string>|null $title
 * @property array<string, string>|null $image size => path
 * @property-read Category|null $category
 * @property-read City|null $city
 */
class Banner extends Model
{
    /** Where a banner shows. "slider" is the home slider; the others are paid slots. */
    public const PLACEMENTS = ['slider', 'home', 'list', 'ad'];

    protected $fillable = ['title', 'link_url', 'enabled', 'starts_at', 'ends_at', 'placement', 'advertiser', 'category_id', 'city_id'];

    protected $casts = ['title' => 'array', 'image' => 'array', 'enabled' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    protected static function booted(): void
    {
        $forget = function () {
            Cache::forget('iibsom.home.banners');
            PageCache::bump();
        };
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Active banners for the home page, cached as plain arrays (5 minutes, cleared on change).
     * Links go through /b/{id} so clicks are counted.
     *
     * @return list<array{title: array<string, string>|null, link_url: ?string, small: ?string, wide: ?string}>
     */
    public static function forHome(): array
    {
        return Cache::remember('iibsom.home.banners', 300, fn () => array_map(
            fn (Banner $b) => ['title' => $b->title, 'link_url' => $b->link_url ? route('banner.click', ['banner' => $b->id]) : null, 'small' => $b->imageUrl('small'), 'wide' => $b->imageUrl('wide')],
            array_values(static::active()->where('placement', 'slider')->orderBy('sort')->get()->all()),
        ));
    }

    /**
     * ON, has an image, and today is between start and end.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('enabled', true)->whereNotNull('image')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * One banner ad for a paid slot: aimed at this category (or its main
     * category) and city, or at everything. A random one when several fit.
     */
    public static function pick(string $placement, ?Category $category = null, ?int $cityId = null): ?self
    {
        if (! feature('banner_ads') || $placement === 'slider') {
            return null;
        }
        $categoryIds = array_values(array_filter([$category?->id, $category?->parent_id]));

        return static::active()->where('placement', $placement)->whereNotNull('link_url')
            ->where(fn ($q) => $q->whereNull('category_id')->when($categoryIds !== [], fn ($w) => $w->orWhereIn('category_id', $categoryIds)))
            ->where(fn ($q) => $q->whereNull('city_id')->when($cityId !== null, fn ($w) => $w->orWhere('city_id', $cityId)))
            ->inRandomOrder()->first();
    }

    /** Where a click goes: our own page or an https page. */
    public function target(): ?string
    {
        if ($this->link_url === null || $this->link_url === '') {
            return null;
        }

        return str_starts_with($this->link_url, '/') ? url($this->link_url) : $this->link_url;
    }

    public function imageUrl(string $size = 'wide'): ?string
    {
        $path = $this->image[$size] ?? null;

        return $path ? Storage::disk($this->disk)->url($path) : null;
    }
}
