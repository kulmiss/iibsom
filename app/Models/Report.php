<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const REASONS = ['scam', 'wrong_category', 'sold', 'offensive', 'other'];

    protected $fillable = ['reason', 'details'];

    protected $casts = ['handled_at' => 'datetime', 'is_demo' => 'boolean'];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
