<?php

namespace Database\Factories;

use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverDocumentFactory extends Factory
{
    protected $model = DriverDocument::class;

    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'key' => $this->faker->randomElement(['license', 'id_card', 'vehicle_rc', 'insurance']),
            'label' => $this->faker->words(2, true),
            'status' => 'pending',
            'document_number' => $this->faker->bothify('??####'),
            'issue_date' => $this->faker->dateTimeBetween('-5 years', '-1 year'),
            'expiry_date' => $this->faker->dateTimeBetween('+1 year', '+5 years'),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejection_reason' => 'Document is not clear',
        ]);
    }
}
