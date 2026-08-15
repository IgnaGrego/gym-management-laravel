<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Health and optional contact fields default to null so factory-created
     * clients exercise the standalone record shape (SPEC-002 BR-001).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'dni' => fake()->unique()->numerify('########'),
            'email' => null,
            'phone' => null,
            'emergency_contact' => null,
            'injuries_notes' => null,
            'medical_conditions_notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Client::STATUS_PENDING,
        ]);
    }
}
