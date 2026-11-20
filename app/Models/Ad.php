<?php

namespace App\Models;

use App\Enums\AdStatus;
use App\Support\Ads\AdCounters;
use App\Support\Cache\PageCache;
use App\Support\Safety\ScamNumbers;
use App\Support\Search\AdFilters;
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
            'bumped_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Scam number list (PR 109): an ad that uses a listed number never goes live; it waits for staff.
        static::saving(function (Ad $ad) {
            if ($ad->status === AdStatus::Live && ($ad->isDirty('status') || ! $ad->exists) && ScamNumbers::blocksAd($ad)) {
                $ad->status = AdStatus::Pending;
                $ad->flag = ScamNumbers::FLAG;
            }
        });
        // Live-ad counters on categories and cities follow every change.
        // Fast filters (PR 90): the detail values in their own indexed table.
        static::saved(function (Ad $ad) {
            if ($ad->wasRecentlyCreated || $ad->wasChanged('fields')) {
                AdFilters::sync($ad);
            }
        });
        // Its own page at once, the lists soon after (PR 90: the rest of the cache stays).
        static::saved(fn (Ad $ad) => PageCache::adChanged($ad->id));
        static::deleted(fn (Ad $ad) => PageCache::adChanged($ad->id));
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
     * The little the ad cards need about the seller: stars (PR 85, "seller_ratings" + "ads.card_stars") and
     * "2 years on iibsom" (PR 87, "sellers.badges" + "ads.card_years"). A separate relation with only these
     * columns, so it never stands in for the full `user`.
     *
     * @return BelongsTo<User, $this>
     */
    public function cardSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'rating_count', 'rating_sum', 'created_at']);
    }

    /**
     * Loads cardSeller for a list of cards: one query for the whole list, none while the switches are OFF.
     * Not used on a seller's own page or shop (every card is the same seller; it is in the header).
     *
     * @param  Builder<Ad>  $query
     */
    public function scopeWithCardSeller(Builder $query): void
    {
        if (self::cardStarsOn() || self::cardYearsOn()) {
            $query->with('cardSeller');
        }
    }

    public static function cardStarsOn(): bool
    {
        return feature('seller_ratings') && feature('ads.card_stars');
    }

    public static function cardYearsOn(): bool
    {
        return feature('sellers.badges') && feature('ads.card_years');
    }

    /**
     * Adds ready_photos_count (the photo count on ad cards, switch "ads.card_badges"). One small count per ad.
     *
     * @param  Builder<Ad>  $query
     */
    public function scopeWithPhotoCount(Builder $query): void
    {
        if (feature('ads.card_badges')) {
            $query->withCount(['photos as ready_photos_count' => fn ($q) => $q->where('status', 'ready')]);
        }
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
