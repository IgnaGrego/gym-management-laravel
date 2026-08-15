<?php

namespace App\Console\Commands;

use App\Models\Membership;
use Illuminate\Console\Command;

class ExpireMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark pending and active memberships whose end date has passed as expired';

    /**
     * Execute the console command.
     *
     * Materializes the `expired` state (SPEC-004 BR-007, ADR-004): flips
     * pending/active memberships whose end date is strictly before today to
     * `expired`. Idempotent bulk status update; period fields and related
     * records are never touched (BR-011, BR-013). `expired` is terminal
     * (BR-009); `activate()` additionally rejects post-period activations in
     * the window before the next run (AC-15).
     */
    public function handle(): int
    {
        Membership::whereIn('status', [Membership::STATUS_PENDING, Membership::STATUS_ACTIVE])
            ->whereDate('end_date', '<', today()->toDateString())
            ->update(['status' => Membership::STATUS_EXPIRED]);

        return self::SUCCESS;
    }
}
