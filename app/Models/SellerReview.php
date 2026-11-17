<?php

namespace App\Models;

use App\Support\Cache\PageCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's 1–5 star rating of a seller (switch "seller_ratings").
 * Only from a chat about one of the seller's ads in which the seller answered.
 *
 * @property-read User $seller
 * @property-read User $buyer
 * @property-read Ad|null $ad
 */
class SellerReview extends Model
{
    public const COMMENT_MAX = 500;

    protected $fillable = ['stars', 'comment'];

    protected function casts(): array
    {
        return ['stars' => 'integer', 'hidden_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class)->withTrashed();
    }

    /**
     * @param  Builder<SellerReview>  $query
     * @return Builder<SellerReview>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    /** A chat where the buyer may rate: you are the buyer and the seller has answered at least once. */
    public static function canRate(Conversation $conversation, User $user): bool
    {
        return $conversation->buyer_id === $user->id // (a seller can never be the buyer: ChatController::start)
            && $conversation->messages()->where('sender_id', $conversation->seller_id)->exists();
    }

    /** Count again the seller's visible stars (after any change) and refresh cached pages. */
    public static function recount(int $sellerId): void
    {
        $row = self::where('seller_id', $sellerId)->visible()->selectRaw('COUNT(*) AS c, COALESCE(SUM(stars), 0) AS s')->first();
        User::whereKey($sellerId)->update(['rating_count' => (int) ($row->c ?? 0), 'rating_sum' => (int) ($row->s ?? 0)]);
        PageCache::bump(); // the stars show on cached ad and shop pages
    }
}
