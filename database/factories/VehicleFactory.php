<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\DriverProfile;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'city_id' => City::factory(),
            'type' => $this->faker->randomElement(['car', 'bike', 'suv', 'van', 'other']),
            'make' => $this->faker->randomElement(['Toyota', 'Honda', 'Ford', 'BMW', 'Mercedes']),
            'model' => $this->faker->word(),
            'year' => $this->faker->numberBetween(2010, now()->year),
            'color' => $this->faker->colorName(),
            'plate_number' => $this->faker->bothify('??####'),
            'seats_total' => $this->faker->numberBetween(2, 8),
            'seats_available_default' => function (array $attributes) {
                return $attributes['seats_total'] - 1;
            },
            'is_active' => true,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
