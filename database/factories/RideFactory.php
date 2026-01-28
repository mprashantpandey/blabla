<?php

namespace Database\Factories;

use App\Models\Ride;
use App\Models\DriverProfile;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class RideFactory extends Factory
{
    protected $model = Ride::class;

    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'city_id' => City::factory(),
            'status' => 'draft',
            'origin_name' => $this->faker->address(),
            'origin_lat' => $this->faker->latitude(),
            'origin_lng' => $this->faker->longitude(),
            'destination_name' => $this->faker->address(),
            'destination_lat' => $this->faker->latitude(),
            'destination_lng' => $this->faker->longitude(),
            'departure_at' => $this->faker->dateTimeBetween('+1 hour', '+30 days'),
            'arrival_estimated_at' => null,
            'price_per_seat' => $this->faker->randomFloat(2, 10, 500),
            'currency_code' => 'USD',
            'seats_total' => $this->faker->numberBetween(2, 8),
            'seats_available' => function (array $attributes) {
                return $attributes['seats_total'];
            },
            'allow_instant_booking' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'departure_at' => now()->addHours(2),
        ]);
    }
}
