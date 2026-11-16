<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Region $region
 * @property array<string, string> $name {"en": "...", "so": "..."}
 */
class City extends Model
{
    use HasTranslatedName;

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'popular' => 'boolean'];

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return HasMany<Area, $this> */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class)->orderBy('sort');
    }

    /**
     * A city is live only when it AND its region are ON.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('cities.enabled', true)
            ->whereHas('region', fn (Builder $q) => $q->where('enabled', true));
    }
}
