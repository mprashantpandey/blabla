<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        $name = $this->faker->city();
        
        return [
            'name' => $name,
            'slug' => City::generateSlug($name),
            'country' => $this->faker->countryCode(),
            'state' => $this->faker->state(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'currency_code' => 'USD',
            'timezone' => 'UTC',
            'default_search_radius_km' => 30.0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
