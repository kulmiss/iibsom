<?php

namespace Database\Factories;

use App\Models\AdPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdPhoto> */
class AdPhotoFactory extends Factory
{
    public function definition(): array
    {
        $key = 'ads/test/'.$this->faker->uuid();

        return [
            'user_id' => User::factory(),
            'disk' => 'public',
            'status' => 'ready',
            'width' => 1200,
            'height' => 900,
            'sort' => 0,
            'variants' => [
                'thumb' => ['webp' => $key.'-thumb.webp'],
                'medium' => ['webp' => $key.'-medium.webp'],
                'large' => ['webp' => $key.'-large.webp'],
            ],
        ];
    }
}
