<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One ad shared (or waiting / failed) on one network. */
class SocialPost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'attempts' => 'integer'];
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class)->withTrashed();
    }
}
