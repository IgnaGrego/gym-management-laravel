<?php

namespace Database\Factories;

use App\Models\Routine;
use App\Models\RoutineDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineDay>
 */
class RoutineDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the ordinal-day shape (SPEC-010 BR-003, D-10 option 2): a day
     * belonging to a routine version with a positive day number. The day
     * number is unique within the version (BR-010, ERR-002); callers that
     * create several days for the same version must pass distinct day
     * numbers.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routine_id' => Routine::factory(),
            'day_number' => 1,
        ];
    }
}
