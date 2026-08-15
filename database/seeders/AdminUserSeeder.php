<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the initial ADMIN user so the system is usable from the start
     * (SPEC-001 FR-008, AC-11, assumption A-03).
     *
     * Credentials source (OQ-06 default, architecture SPEC-001 §5):
     * environment variables ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD
     * (documented in .env.example).
     *
     * - In production, all three variables are required; the seeder aborts
     *   with a clear message if any is missing (no hardcoded credentials).
     * - Outside production, missing values fall back to documented local-dev
     *   defaults so the application can be bootstrapped locally.
     *
     * Idempotent: an existing user with the same email is not duplicated and
     * the ADMIN role is attached only when missing.
     */
    public function run(): void
    {
        $name = env('ADMIN_NAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $name || ! $email || ! $password) {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'AdminUserSeeder requires ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD in production.'
                );
            }

            $name = $name ?: 'Admin';
            $email = $email ?: 'admin@gym.test';
            $password = $password ?: 'password';
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_active' => true,
            ]
        );

        $adminRole = Role::firstOrCreate(['name' => Role::ADMIN]);

        if (! $user->hasRole(Role::ADMIN)) {
            $user->roles()->attach($adminRole);
        }
    }
}
