<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Only these can be mass-assigned (never banned_at, google_id from user input, etc.). */
    protected $fillable = ['name', 'phone', 'phone_has_whatsapp', 'city_id'];

    protected $hidden = ['password', 'remember_token', 'google_id'];

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
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
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
