<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Cuota;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\RoutineAssignment;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDemoDatabase extends Command
{
    /**
     * Wipe transactional/demo data so a public demo instance cannot be
     * polluted or abused, then reseed fresh demo content.
     *
     * Keeps reference/master data (roles, plans, exercises, turnos, routines)
     * and the ADMIN user intact. The demo reset is idempotent and safe to run
     * repeatedly (e.g. nightly via the Laravel scheduler).
     */
    protected $signature = 'demo:reset';

    protected $description = 'Reset the demo database to a clean state and reseed demo data';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('demo:reset is disabled in production.');
            return self::FAILURE;
        }

        $this->info('Resetting demo data...');

        DB::transaction(function () {
            // Transactional data tied to clients/users — wipe first.
            WorkoutLog::query()->delete();
            Attendance::query()->delete();
            Booking::query()->delete();
            Payment::query()->delete();
            Cuota::query()->delete();
            Membership::query()->delete();
            RoutineAssignment::query()->delete();

            // Demo clients and their linked users (keep the real ADMIN user).
            $clientUserIds = Client::pluck('user_id')->filter();
            Client::query()->delete();
            if ($clientUserIds->isNotEmpty()) {
                User::whereIn('id', $clientUserIds)->delete();
            }
        });

        $this->callSilently('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder']);

        $this->info('Demo data reset complete.');

        return self::SUCCESS;
    }
}
