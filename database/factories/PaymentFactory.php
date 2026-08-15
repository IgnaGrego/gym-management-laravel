<?php

namespace Database\Factories;

use App\Models\Cuota;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Payment shape (SPEC-005 FR-004, BR-005, BR-007): a cuota, a
     * positive amount, method `cash`, today's payment date, no reference and
     * no notes, status `confirmed` (the model default — the only status a
     * SPEC-005 flow produces) and a recording User (recorded_by is required,
     * PY-06).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cuota_id' => Cuota::factory(),
            'amount' => fake()->randomFloat(2, 1, 100000),
            'method' => Payment::METHOD_CASH,
            'payment_date' => now()->toDateString(),
            'reference' => null,
            'notes' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
