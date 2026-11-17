<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Only these can be mass-assigned (never banned_at, google_id from user input, etc.). */
    protected $fillable = ['name', 'phone', 'phone_has_whatsapp', 'city_id'];

    protected $hidden = ['password', 'remember_token', 'google_id', 'two_factor_secret', 'two_factor_recovery_codes'];

    public const CUSTOMER = 'customer';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'banned_at' => 'datetime',
            'phone_has_whatsapp' => 'boolean',
            'verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** Super Admin, Moderator or Support. */
    public function isStaff(): bool
    {
        return array_key_exists($this->role, config()->array('admin.roles'));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        if (! $this->isStaff()) {
            return [];
        }

        $permissions = config("admin.roles.{$this->role}.permissions", []);

        return $permissions === ['*'] ? array_keys(config('admin.permissions')) : $permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || in_array($permission, $this->permissions(), true);
    }

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /**
     * Ads this user saved (switch "favourites").
     *
     * @return BelongsToMany<Ad, $this>
     */
    public function favourites(): BelongsToMany
    {
        return $this->belongsToMany(Ad::class, 'favourites')->withTimestamps();
    }

    /** @return HasMany<SavedSearch, $this> */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /** @return HasMany<VerificationRequest, $this> */
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    /** Average stars (one decimal), or null with no ratings yet. */
    public function rating(): ?float
    {
        return $this->rating_count > 0 ? round($this->rating_sum / $this->rating_count, 1) : null;
    }

    /** @return HasMany<SellerReview, $this> */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(SellerReview::class, 'seller_id');
    }

    /** @return HasMany<ApiToken, $this> */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Shop, $this> */
    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    /** Shows the "Verified" badge (only while the switch is ON). */
    public function isVerified(): bool
    {
        return $this->verified_at !== null && feature('verified_badge');
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /** Name, city, phone and terms are filled in → can post ads. */
    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed_at !== null;
    }

    /** First letter for the avatar circle when there is no photo. */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name) ?: '?', 0, 1));
    }
}
