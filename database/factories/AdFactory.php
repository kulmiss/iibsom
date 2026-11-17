<?php

namespace Database\Factories;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ad>
 */
class AdFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->words(4, true);

        return [
            'user_id' => User::factory()->completed(),
            'category_id' => fn () => Category::whereNotNull('parent_id')->inRandomOrder()->value('id'),
            'city_id' => fn () => City::where('slug', 'mogadishu')->value('id'),
            'title' => Str::ucfirst($title),
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(10, 20000),
            'price_negotiable' => false,
            'condition' => 'used',
            'fields' => [],
            'status' => AdStatus::Pending,
        ];
    }

    public function live(): static
    {
        return $this->state(fn () => ['status' => AdStatus::Live, 'published_at' => now(), 'expires_at' => now()->addDays(60)]);
    }
}
