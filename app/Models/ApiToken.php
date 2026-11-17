<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A personal API token: "iib_<id>_<secret>". Only a hash of the secret is
 * stored, so the full token is shown once, when it is made.
 *
 * @property list<string> $abilities
 * @property-read User $user
 */
class ApiToken extends Model
{
    public const ABILITIES = ['read', 'write'];

    public const MAX_PER_USER = 5;

    public const DAYS = 365;

    protected $fillable = ['name', 'abilities'];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return ['abilities' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  list<string>  $abilities
     * @return array{0: ApiToken, 1: string} the token and its plain text (shown once)
     */
    public static function issue(User $user, string $name, array $abilities): array
    {
        $secret = Str::random(40);
        $token = new self(['name' => $name, 'abilities' => array_values(array_intersect(self::ABILITIES, $abilities))]);
        $token->secret_hash = hash('sha256', $secret);
        $token->expires_at = now()->addDays(self::DAYS);
        $user->apiTokens()->save($token);

        return [$token, "iib_{$token->id}_{$secret}"];
    }

    /** The token for "iib_<id>_<secret>", or null (constant-time check). */
    public static function findByKey(string $plain): ?self
    {
        if (preg_match('/^iib_(\d{1,18})_([A-Za-z0-9]{40})$/', $plain, $m) !== 1) {
            return null;
        }
        $token = self::with('user')->find((int) $m[1]);
        if ($token === null || ! hash_equals($token->secret_hash, hash('sha256', $m[2])) || $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }
}
