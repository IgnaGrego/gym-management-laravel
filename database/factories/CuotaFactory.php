<?php

namespace Database\Factories;

use App\Models\Cuota;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cuota>
 */
class CuotaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Mirrors the Cuota shape (SPEC-005 FR-001, BR-001, BR-002, BR-003): a
     * membership, a positive amount and status `pending`. Because Membership
     * auto-generates its cuota via the `created` hook (ADR-005), the factory
     * creates the membership WITHOUT model events (so no auto-cuota is
     * generated) and supplies the computed end_date explicitly (the `creating`
     * hook that would compute it is also disabled). This keeps the cuota the
     * factory produces the single cuota for its membership, avoiding the
     * membership_id UNIQUE violation (NC-02).
     *
     * SPEC-005 tests that need the auto-generated cuota should obtain it via
     * $membership->cuota instead of constructing a second one.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => fn () => Membership::withoutEvents(fn (): int => Membership::factory()->create([
                'end_date' => now()->addDays(29)->toDateString(),
            ])->id),
            'amount' => fake()->randomFloat(2, 1, 100000),
            'status' => Cuota::STATUS_PENDING,
        ];
    }
}
