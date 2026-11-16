<?php

namespace App\Models;

use App\Support\Cache\PageCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $fillable = ['title', 'link_url', 'enabled', 'starts_at', 'ends_at'];

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
     *
     * @return list<array{title: array, link_url: ?string, small: ?string, wide: ?string}>
     */
    public static function forHome(): array
    {
        return Cache::remember('iibsom.home.banners', 300, fn () => static::active()->orderBy('sort')->get()
            ->map(fn (Banner $b) => ['title' => $b->title, 'link_url' => $b->link_url, 'small' => $b->imageUrl('small'), 'wide' => $b->imageUrl('wide')])
            ->all());
    }

    /** ON, has an image, and today is between start and end. */
    public function scopeActive(Builder $query): void
    {
        $query->where('enabled', true)->whereNotNull('image')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function imageUrl(string $size = 'wide'): ?string
    {
        $path = $this->image[$size] ?? null;

        return $path ? Storage::disk($this->disk)->url($path) : null;
    }
}
