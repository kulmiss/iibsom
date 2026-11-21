<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "This is my number, please check again" (PR 109). Staff decide; the decision is logged.
 *
 * @property-read ScamNumber $number
 */
class ScamAppeal extends Model
{
    /** The contact (how to answer) is removed this many days after the decision. */
    public const KEEP_CONTACT_DAYS = 30;

    protected $fillable = ['message', 'contact'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** Nightly: remove the contacts of requests decided more than 30 days ago. */
    public static function pruneContacts(): int
    {
        return self::whereNotNull('contact')->where('decided_at', '<', now()->subDays(self::KEEP_CONTACT_DAYS))->update(['contact' => null]);
    }

    /** @return BelongsTo<ScamNumber, $this> */
    public function number(): BelongsTo
    {
        return $this->belongsTo(ScamNumber::class, 'scam_number_id');
    }
}
