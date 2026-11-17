<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Ad $ad
 * @property-read User|null $user (null = a guest report)
 */
class Report extends Model
{
    public const REASONS = ['scam', 'wrong_category', 'sold', 'offensive', 'other'];

    protected $fillable = ['reason', 'details'];

    protected $casts = ['handled_at' => 'datetime', 'is_demo' => 'boolean'];

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
