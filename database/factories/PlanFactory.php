<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Plan catalog shape (SPEC-003 FR-001): a unique name, an
     * optional description and enrollment fee (defaulting to null so absent
     * optional fields exercise the standalone record), a positive price, and
     * active by default (AP-02).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'price' => fake()->randomFloat(2, 1, 100000),
            'enrollment_fee' => null,
            'is_active' => true,
        ];
    }
}
