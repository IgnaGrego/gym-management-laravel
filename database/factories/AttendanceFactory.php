<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Attendance shape (SPEC-008 FR-001, BR-002, BR-011, AT-05,
     * AT-06): a client, the access timestamp defaulting to now, the staff
     * User who recorded the check-in (recorded_by is required, BR-011) and
     * the optional turno link and notes defaulting to null.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'attended_at' => now(),
            'recorded_by' => User::factory(),
            'turno_id' => null,
            'notes' => null,
        ];
    }
}
