<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Membership;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Membership shape (SPEC-004 FR-001): a client, a plan, a
     * start date, a 30-day duration and status `pending` (BR-005). end_date is
     * intentionally not set here: the model's creating hook computes it as
     * start_date + duration_days - 1 (BR-003). Tests that need a specific
     * period pass end_date explicitly and the hook does not overwrite it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'plan_id' => Plan::factory(),
            'start_date' => now()->toDateString(),
            'duration_days' => 30,
            'status' => Membership::STATUS_PENDING,
        ];
    }
}
