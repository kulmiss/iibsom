<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One paid boost period of an ad (history; the ad itself keeps the current level).
 *
 * @property-read Ad $ad
 * @property-read Payment $payment
 */
class AdBoost extends Model
{
    protected $fillable = ['ad_id', 'payment_id', 'package', 'level', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
