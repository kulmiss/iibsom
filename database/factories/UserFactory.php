<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'google_id' => (string) fake()->unique()->numerify('1##################'),
            'password' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /** Profile filled in (name, city, phone, terms) → can post ads. */
    public function completed(): static
    {
        return $this->state(fn () => [
            'phone' => '+25261'.fake()->unique()->numerify('#######'),
            'phone_has_whatsapp' => true,
            'terms_accepted_at' => now(),
            'profile_completed_at' => now(),
        ]);
    }

    public function banned(string $reason = 'Scam'): static
    {
        return $this->state(fn () => ['banned_at' => now(), 'ban_reason' => $reason]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
