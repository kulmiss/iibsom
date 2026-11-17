<?php

namespace App\Models;

use App\Support\Files\PrivateFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mobile-money payment sent outside the site and proven by a receipt
 * (switch "boosts"). Staff approve it; approval writes the ledger.
 *
 * @property-read User $user
 * @property-read Ad|null $ad
 * @property-read User|null $reviewer
 */
class Payment extends Model
{
    protected $fillable = ['ad_id', 'purpose', 'package', 'method', 'amount_cents', 'currency', 'reference', 'receipt_path', 'is_test'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'is_test' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function receiptBytes(): ?string
    {
        return PrivateFiles::get($this->receipt_path);
    }

    /** "$8.00" — always from whole cents. */
    public static function money(int $cents): string
    {
        return '$'.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
