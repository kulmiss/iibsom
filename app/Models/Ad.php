<?php

namespace App\Models;

use App\Enums\AdStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ad extends Model
{
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
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'sold_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AdPhoto::class)->orderBy('sort');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeLive(Builder $query): void
    {
        $query->where('status', AdStatus::Live)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
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
