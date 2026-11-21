<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read City $city
 * @property array<string, string> $name {"en": "...", "so": "..."}
 */
class Area extends Model
{
    use HasTranslatedName;

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'needs_review' => 'boolean'];

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
