<?php

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\User;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverProfileFactory extends Factory
{
    protected $model = DriverProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'city_id' => City::factory(),
            'status' => 'not_applied',
            'dob' => $this->faker->dateTimeBetween('-60 years', '-18 years'),
            'address' => $this->faker->address(),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'applied_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'applied_at' => now()->subDays(5),
            'verified_at' => now()->subDays(4),
        ]);
    }
}
