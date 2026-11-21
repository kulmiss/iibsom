<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A phone number staff marked "Reported for scams" (PR 109). Never automatic, never a person's name.
 *
 * @property-read User|null $marker
 */
class ScamNumber extends Model
{
    protected $fillable = ['phone', 'reason', 'proof'];

    protected function casts(): array
    {
        return ['removed_at' => 'datetime'];
    }

    /** @param  Builder<ScamNumber>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    /** @return BelongsTo<User, $this> */
    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /** @return HasMany<ScamAppeal, $this> */
    public function appeals(): HasMany
    {
        return $this->hasMany(ScamAppeal::class);
    }
}
