<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutLog>
 */
class WorkoutLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the WorkoutLog shape (SPEC-011 BR-001, BR-008, BR-009, WL-05,
     * WL-06, WL-11): a client, the performed timestamp defaulting to now, the
     * staff User who recorded the log (recorded_by is required, BR-009), and
     * the optional fields (routine_exercise_id / exercise_id / actual_weight /
     * notes) defaulting to null. The factory intentionally does not pick a
     * default reference so each test states the case explicitly: callers set
     * exactly one of routine_exercise_id / exercise_id per BR-002.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'performed_at' => now(),
            'routine_exercise_id' => null,
            'exercise_id' => null,
            'actual_weight' => null,
            'actual_reps' => 10,
            'notes' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
