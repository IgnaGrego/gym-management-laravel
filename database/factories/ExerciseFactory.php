<?php

namespace Database\Factories;

use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Exercise catalog shape (SPEC-009 FR-001): a unique name
     * (BR-003), a required muscle group from the fixed set (BR-004), absent
     * optional fields (equipment, difficulty, instructions, video_url)
     * defaulting to null so the optional fields exercise the standalone
     * record, and active by default (BR-007, EX-07).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'muscle_group' => Exercise::MUSCLE_GROUP_CHEST,
            'equipment' => null,
            'difficulty' => null,
            'instructions' => null,
            'video_url' => null,
            'is_active' => true,
        ];
    }
}
