<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedPhone extends Model
{
    protected $fillable = ['phone', 'reason'];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public static function isBlocked(?string $phone): bool
    {
        return $phone !== null && static::where('phone', $phone)->exists();
    }
}
