<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A private chat between a buyer and a seller about one ad (switch "chat").
 *
 * @property-read Ad|null $ad
 * @property-read User $buyer
 * @property-read User $seller
 */
class Conversation extends Model
{
    protected $fillable = ['ad_id', 'buyer_id', 'seller_id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'last_message_id' => 'integer', 'buyer_read_id' => 'integer', 'seller_read_id' => 'integer'];
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @param  Builder<Conversation>  $query */
    public function scopeFor(Builder $query, User $user): void
    {
        $query->where(fn ($q) => $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id));
    }

    public function has(User $user): bool
    {
        return $user->id === $this->buyer_id || $user->id === $this->seller_id;
    }

    public function other(User $user): User
    {
        return $user->id === $this->buyer_id ? $this->seller : $this->buyer;
    }

    public function isUnreadFor(User $user): bool
    {
        // Sending also moves the sender's read mark, so one's own messages never count as unread.
        return $this->last_message_id > ($user->id === $this->buyer_id ? $this->buyer_read_id : $this->seller_read_id);
    }

    public function markRead(User $user): void
    {
        $this->forceFill([$user->id === $this->buyer_id ? 'buyer_read_id' : 'seller_read_id' => $this->last_message_id])->save();
    }

    /** Unread conversations for the menu dot (one small query). */
    public static function unreadCount(User $user): int
    {
        return static::query()
            ->where(fn ($q) => $q
                ->where(fn ($b) => $b->where('buyer_id', $user->id)->whereColumn('buyer_read_id', '<', 'last_message_id'))
                ->orWhere(fn ($s) => $s->where('seller_id', $user->id)->whereColumn('seller_read_id', '<', 'last_message_id')))
            ->count();
    }
}
