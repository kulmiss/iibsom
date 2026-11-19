<?php

namespace App\Models;

use App\Search\SearchCriteria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A search a user saved; the daily alert emails new matching ads.
 *
 * @phpstan-import-type Criteria from SearchCriteria
 *
 * @property Criteria $criteria
 * @property-read User $user
 */
class SavedSearch extends Model
{
    public const MAX_PER_USER = 20;

    protected $fillable = ['label', 'criteria', 'hash', 'checked_until'];

    protected function casts(): array
    {
        return [
            'muted' => 'boolean',
            'criteria' => 'array',
            'checked_until' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The results page for this search (in the current language). */
    public function url(): string
    {
        $c = $this->criteria;
        $query = array_filter([
            'q' => $c['q'], 'min' => $c['min'], 'max' => $c['max'], 'condition' => $c['condition'],
            'area' => $c['area_id'] ?? null,
            'photos' => ! empty($c['photos']) ? 1 : null,
            'verified' => ! empty($c['verified']) ? 1 : null,
            'f' => $c['fields'] ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $category = $c['category_id'] !== null ? Category::find($c['category_id']) : null;
        $city = $c['city_id'] !== null ? City::find($c['city_id']) : null;

        if ($category !== null) {
            return lroute('browse.category', array_filter(['category' => $category->slug, 'city' => $city?->slug]) + $query);
        }

        return lroute('search', $query + array_filter(['city' => $city?->slug]));
    }
}
