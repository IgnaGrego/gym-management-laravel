<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineExercise>
 */
class RoutineExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the set-level prescription row shape (SPEC-010 BR-004, D-11
     * option 2; AR-06): one row per set referencing an exercise, with a
     * positive set number, required positive target reps, and the optional
     * fields (target_weight, rest_seconds, notes) absent by default so the
     * optional fields exercise the standalone record shape. The set number is
     * unique within the day (BR-010, ERR-002); callers that create several
     * rows for the same day must pass distinct set numbers.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routine_day_id' => RoutineDay::factory(),
            'exercise_id' => Exercise::factory(),
            'set_number' => 1,
            'target_reps' => 10,
            'target_weight' => null,
            'rest_seconds' => null,
            'notes' => null,
        ];
    }
}
