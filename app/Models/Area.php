<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read City $city
 * @property array<string, string> $name {"en": "...", "so": "..."}
 */
class Area extends Model
{
    use HasTranslatedName;

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean'];

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
