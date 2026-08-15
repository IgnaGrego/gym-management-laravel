<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Booking shape (SPEC-007 FR-001, BR-002, BR-003, BK-12): a
     * client, a turno, status `confirmed` (BR-003), booked_at defaulting to now
     * (FR-001), the staff User who created the booking (booked_by, BK-12) and
     * optional notes defaulting to null.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'turno_id' => Turno::factory(),
            'status' => Booking::STATUS_CONFIRMED,
            'booked_at' => now(),
            'booked_by' => User::factory(),
            'notes' => null,
        ];
    }
}
