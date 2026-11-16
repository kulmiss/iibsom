<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Category extends Model
{
    use HasTranslatedName;

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'popular' => 'boolean', 'has_condition' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CategoryField::class)->orderBy('sort');
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** Fields for the "post an ad" form and filters: parent's fields first, then own. */
    public function allFields(): Collection
    {
        $parentFields = $this->parent?->fields()->where('enabled', true)->get() ?? collect();
        $own = $this->fields()->where('enabled', true)->get();

        return $parentFields->concat($own)->keyBy('key')->values();
    }

    /** Live only if this category and its parent are ON. */
    public function isLive(): bool
    {
        return $this->enabled && ($this->parent === null || $this->parent->enabled);
    }
}
