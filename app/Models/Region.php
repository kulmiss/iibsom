<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, string> $name {"en": "...", "so": "..."}
 */
class Region extends Model
{
    use HasTranslatedName;

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean'];

    /** @return HasMany<City, $this> */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class)->orderBy('sort');
    }

    /** @param  Builder<self>  $query */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }
}
