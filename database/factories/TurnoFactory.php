<?php

namespace Database\Factories;

use App\Models\Turno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turno>
 */
class TurnoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Turno shape (SPEC-006 FR-001, BR-005, BR-007, BR-002): a
     * valid same-day interval on today (BR-006) with a positive capacity and
     * status `active` (FR-001, BR-002, AS-07). label defaults to null so the
     * optional field exercises the standalone record shape.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => today()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'capacity_limit' => 10,
            'status' => Turno::STATUS_ACTIVE,
            'label' => null,
        ];
    }
}
