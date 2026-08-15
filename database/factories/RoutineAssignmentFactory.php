<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineAssignment>
 */
class RoutineAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the assignment shape (SPEC-010 BR-007, AR-03, AR-09): a client
     * assigned to a routine VERSION with an assignment timestamp and an
     * active flag defaulting to true. History is preserved by deactivating
     * rows, never deleting them.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'routine_id' => Routine::factory(),
            'assigned_at' => now(),
            'is_active' => true,
        ];
    }
}
