<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One Watchdog problem (PR 94). Open while fixed_at is empty.
 *
 * @property int $id
 * @property string $key
 * @property string $area
 * @property string $level
 * @property string $title
 * @property string $message
 * @property list<string>|null $steps
 * @property array<string, mixed>|null $data
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int $count
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $alerted_at
 * @property Carbon|null $last_alert_at
 * @property bool $pending_alert
 * @property Carbon|null $muted_until
 * @property int|null $muted_by
 * @property Carbon|null $fixed_at
 * @property int|null $fixed_by
 * @property string|null $fixed_how
 */
class WatchdogProblem extends Model
{
    public const LEVELS = ['urgent' => 3, 'warning' => 2, 'info' => 1];

    protected $guarded = ['id', 'open_key'];

    protected $casts = [
        'steps' => 'array',
        'data' => 'array',
        'pending_alert' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'alerted_at' => 'datetime',
        'last_alert_at' => 'datetime',
        'muted_until' => 'datetime',
        'fixed_at' => 'datetime',
    ];

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('fixed_at');
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    /** @return BelongsTo<User, $this> */
    public function fixer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fixed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function muter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_by');
    }
}
