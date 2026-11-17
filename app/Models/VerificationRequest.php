<?php

namespace App\Models;

use App\Support\Files\PrivateFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller's request for the Verified badge. The ID photo is stored
 * encrypted on the private disk and deleted as soon as staff decide.
 *
 * @property-read User $user
 * @property-read User|null $reviewer
 */
class VerificationRequest extends Model
{
    public const ID_TYPES = ['national_id', 'passport', 'driving_licence'];

    protected $fillable = ['id_type', 'file_path'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Keep the photo encrypted at rest. */
    public static function storePhoto(string $bytes): string
    {
        return PrivateFiles::put('verifications', $bytes);
    }

    public function photoBytes(): ?string
    {
        return PrivateFiles::get($this->file_path);
    }

    public function deletePhoto(): void
    {
        PrivateFiles::delete($this->file_path);
        $this->file_path = null;
    }
}
