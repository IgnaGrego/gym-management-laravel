<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Exercise;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds fresh, clearly fake demo data for a public portfolio demo instance.
 *
 * This seeder is idempotent: it only creates reference data (roles, plans,
 * exercises, a routine, turnos) when absent and always (re)creates the demo
 * client account. It is invoked by the `demo:reset` command (ResetDemoDatabase)
 * after transactional data is wiped, so the public demo always returns to a
 * clean, known state regardless of any abuse in between.
 */
class DemoSeeder extends Seeder
{
    /**
     * Demo client credentials shared publicly for the portfolio demo.
     */
    private const DEMO_CLIENT_EMAIL = 'cliente@gym.com';

    private const DEMO_CLIENT_PASSWORD = 'Cliente123!';

    public function run(): void
    {
        // Roles must exist before any role assignment.
        $adminRole = Role::firstOrCreate(['name' => Role::ADMIN]);
        $clientRole = Role::firstOrCreate(['name' => Role::CLIENT]);

        // An ADMIN user is required as the author of reference data (created_by).
        $adminUser = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@gym.test')],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => env('ADMIN_PASSWORD', 'password'),
                'is_active' => true,
            ]
        );
        $adminUser->roles()->syncWithoutDetaching($adminRole);

        // A single demo plan for the memberships to reference.
        $plan = Plan::firstOrCreate(
            ['name' => 'Plan Full'],
            [
                'description' => 'Acceso completo a todas las instalaciones.',
                'price' => '15000.00',
                'enrollment_fee' => '0.00',
                'is_active' => true,
            ]
        );

        // A small exercise catalogue so routines have something to reference.
        $squat = Exercise::firstOrCreate(
            ['name' => 'Sentadilla'],
            ['muscle_group' => 'Piernas', 'is_active' => true]
        );
        $press = Exercise::firstOrCreate(
            ['name' => 'Press de banca'],
            ['muscle_group' => 'Pecho', 'is_active' => true]
        );
        $pull = Exercise::firstOrCreate(
            ['name' => 'Remo con barra'],
            ['muscle_group' => 'Espalda', 'is_active' => true]
        );

        // One sample active routine with a couple of days.
        $routine = Routine::firstOrCreate(
            ['name' => 'Rutina Full Body'],
            [
                'status' => Routine::STATUS_ACTIVE,
                'version_number' => 1,
                'created_by' => $adminUser->id,
            ]
        );
        if ($routine->days()->doesntExist()) {
            $day1 = RoutineDay::create(['routine_id' => $routine->id, 'day_number' => 1]);
            RoutineExercise::create([
                'routine_day_id' => $day1->id,
                'exercise_id' => $squat->id,
                'set_number' => 1,
                'target_reps' => 10,
                'target_weight' => '40.00',
                'rest_seconds' => 90,
            ]);
            RoutineExercise::create([
                'routine_day_id' => $day1->id,
                'exercise_id' => $press->id,
                'set_number' => 2,
                'target_reps' => 8,
                'target_weight' => '30.00',
                'rest_seconds' => 90,
            ]);
            RoutineExercise::create([
                'routine_day_id' => $day1->id,
                'exercise_id' => $pull->id,
                'set_number' => 3,
                'target_reps' => 12,
                'target_weight' => '25.00',
                'rest_seconds' => 60,
            ]);
        }

        // A few upcoming turnos so the portal's booking view has content.
        if (! Turno::query()->whereDate('date', '>=', today())->exists()) {
            for ($i = 1; $i <= 4; $i++) {
                Turno::create([
                    'date' => today()->addDays($i),
                    'start_time' => '09:00',
                    'end_time' => '10:00',
                    'capacity_limit' => 15,
                    'status' => Turno::STATUS_ACTIVE,
                    'label' => 'Turno mañana',
                ]);
            }
        }

        // Recreate the demo CLIENT account (delete-then-create keeps it clean).
        $existing = User::where('email', self::DEMO_CLIENT_EMAIL)->first();
        if ($existing) {
            Client::where('user_id', $existing->id)->delete();
            $existing->delete();
        }

        $clientUser = User::create([
            'name' => 'Cliente Demo',
            'email' => self::DEMO_CLIENT_EMAIL,
            'password' => self::DEMO_CLIENT_PASSWORD,
            'is_active' => true,
        ]);
        $clientUser->roles()->attach($clientRole);

        $client = Client::create([
            'full_name' => 'Cliente Demo',
            'dni' => '36123456',
            'email' => self::DEMO_CLIENT_EMAIL,
            'phone' => '11 5555 1234',
            'emergency_contact' => 'Emergencia Demo - 11 5555 9999',
            'status' => Client::STATUS_ACTIVE,
        ]);
        $client->user()->associate($clientUser);
        $client->save();

        // Active membership so the demo client qualifies for access.
        $start = today();
        Membership::create([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(29),
            'duration_days' => 30,
            'status' => Membership::STATUS_ACTIVE,
        ]);
    }
}
