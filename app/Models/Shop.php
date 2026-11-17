<?php

namespace App\Models;

use App\Support\Cache\PageCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * A business shop page (switch "shops"). Public only while active:
 * an admin sets active_until (monthly plan) and the switch is ON.
 *
 * @property array{large: string, small: string}|null $logo
 * @property-read User $user
 * @property-read City|null $city
 */
class Shop extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'hours', 'city_id'];

    protected function casts(): array
    {
        return ['logo' => 'array', 'active_until' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @param  Builder<Shop>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereDate('active_until', '>=', now('Africa/Mogadishu')->toDateString())
            ->whereHas('user', fn ($q) => $q->whereNull('banned_at'));
    }

    public function isActive(): bool
    {
        return feature('shops')
            && $this->active_until !== null
            && $this->active_until->toDateString() >= now('Africa/Mogadishu')->toDateString()
            && ! $this->user->isBanned();
    }

    public function url(): string
    {
        return lroute('shop.show', ['shop' => $this->slug]);
    }

    public function logoUrl(string $size = 'small'): ?string
    {
        $path = $this->logo[$size] ?? null;

        return $path ? Storage::disk($this->logo_disk ?? config('iibsom.photos_disk'))->url($path) : null;
    }

    /** Re-encode (removes hidden data), square, 2 sizes, WebP, on the photos disk. */
    public function saveLogo(UploadedFile $file): void
    {
        $manager = new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver);
        $disk = (string) config('iibsom.photos_disk');
        $folder = 'shops/'.Str::uuid();
        $old = $this->logo ? [$this->logo_disk, array_values($this->logo)] : null;

        $logo = [];
        foreach (['large' => 400, 'small' => 128] as $size => $px) {
            $img = $manager->read($file->getRealPath())->orient()->cover($px, $px);
            Storage::disk($disk)->put("{$folder}/{$size}.webp", (string) $img->toWebp(quality: 82), ['visibility' => 'public']);
            $logo[$size] = "{$folder}/{$size}.webp";
        }
        $this->logo_disk = $disk;
        $this->logo = $logo;

        if ($old) {
            Storage::disk($old[0] ?? $disk)->delete($old[1]);
        }
    }

    public function deleteLogo(): void
    {
        if ($this->logo) {
            Storage::disk($this->logo_disk ?? config('iibsom.photos_disk'))->delete(array_values($this->logo));
        }
    }

    protected static function booted(): void
    {
        static::deleting(fn (Shop $shop) => $shop->deleteLogo());
        static::saved(fn () => PageCache::bump());
        static::deleted(fn () => PageCache::bump());
    }
}
