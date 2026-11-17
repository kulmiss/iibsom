<?php

namespace App\Models;

use App\Enums\AdStatus;
use App\Support\Ads\AdCounters;
use App\Support\Cache\PageCache;
use App\Support\Social\SocialPosts;
use Database\Factories\AdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Required links (NOT NULL foreign keys in the database):
 *
 * @property-read User $user
 * @property-read Category $category
 * @property-read City $city
 */
class Ad extends Model
{
    /** @use HasFactory<AdFactory> */
    use HasFactory, SoftDeletes;

    /** User input only. Status, review and dates are set by code, never by the form. */
    protected $fillable = ['category_id', 'city_id', 'area_id', 'title', 'description', 'price', 'price_negotiable', 'condition', 'fields'];

    protected function casts(): array
    {
        return [
            'status' => AdStatus::class,
            'fields' => 'array',
            'price_negotiable' => 'boolean',
            'is_demo' => 'boolean',
            'boost_level' => 'integer',
            'boosted_until' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'sold_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Live-ad counters on categories and cities follow every change.
        static::saved(fn () => PageCache::bump());
        static::deleted(fn () => PageCache::bump());
        static::saved(function (Ad $ad) {
            if ($ad->wasChanged(['status', 'category_id', 'city_id', 'expires_at', 'is_demo']) || $ad->wasRecentlyCreated) {
                AdCounters::refreshFor($ad);
            }
        });
        // Auto-post (switch "social.autopost", OFF): share an ad once, when it first goes live.
        static::saved(function (Ad $ad) {
            if ($ad->status === AdStatus::Live && ($ad->wasChanged('status') || $ad->wasRecentlyCreated)) {
                SocialPosts::queueFor($ad);
            }
        });
        static::deleted(fn (Ad $ad) => AdCounters::refreshFor($ad));
        static::restored(fn (Ad $ad) => AdCounters::refreshFor($ad));
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return HasMany<AdPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(AdPhoto::class)->orderBy('sort');
    }

    /**
     * First ready photo (for cards).
     *
     * @return HasOne<AdPhoto, $this>
     */
    public function cover(): HasOne
    {
        return $this->hasOne(AdPhoto::class)->where('status', 'ready')->orderBy('sort');
    }

    /** /cars/mogadishu/toyota-hilux-2016-123 */
    public function url(): string
    {
        return lroute('ad.show', ['category' => $this->category->slug, 'city' => $this->city->slug, 'slugId' => $this->slug.'-'.$this->id]);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @param  Builder<self>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', AdStatus::Live)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Paid boost running now (and the switch is ON): 1 Top, 2 VIP, 3 Diamond, else 0. */
    public function activeBoost(): int
    {
        return feature('boosts') && $this->boost_level > 0 && $this->boosted_until?->isFuture() ? $this->boost_level : 0;
    }

    public function isLive(): bool
    {
        return $this->status === AdStatus::Live && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** "$1,200" or "Negotiable" or "$1,200 · Negotiable". */
    public function priceLabel(): string
    {
        $parts = [];
        if ($this->price !== null) {
            $parts[] = '$'.number_format($this->price);
        }
        if ($this->price_negotiable) {
            $parts[] = __('ads.form.negotiable');
        }

        return implode(' · ', $parts);
    }
}
