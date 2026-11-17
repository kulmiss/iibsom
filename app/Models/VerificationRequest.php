<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

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

    public const DISK = 'local'; // storage/app/private: never served to the web

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
        $path = 'verifications/'.bin2hex(random_bytes(16)).'.bin';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString(base64_encode($bytes)));

        return $path;
    }

    public function photoBytes(): ?string
    {
        if ($this->file_path === null || ! Storage::disk(self::DISK)->exists($this->file_path)) {
            return null;
        }

        return base64_decode(Crypt::decryptString((string) Storage::disk(self::DISK)->get($this->file_path)), true) ?: null;
    }

    public function deletePhoto(): void
    {
        if ($this->file_path !== null) {
            Storage::disk(self::DISK)->delete($this->file_path);
            $this->file_path = null;
        }
    }
}
