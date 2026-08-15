<?php

namespace Database\Factories;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Routine>
 */
class RoutineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Routine version shape (SPEC-010 FR-001, BR-002, AR-01,
     * AR-02): a draft version 1 with no days, no lineage link (replaces_id
     * null) and the required creator FK set (BR-011). name is NOT unique
     * (AR-05).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'status' => Routine::STATUS_DRAFT,
            'version_number' => 1,
            'replaces_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
