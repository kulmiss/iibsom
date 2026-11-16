<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** Super Admin, Moderator or Support. */
    public function isStaff(): bool
    {
        return array_key_exists($this->role, config('admin.roles'));
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

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
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
