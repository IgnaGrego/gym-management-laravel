<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the fixed role catalog (SPEC-001 FR-004, BR-001).
     *
     * Idempotent: existing roles are left untouched.
     */
    public function run(): void
    {
        foreach ([Role::ADMIN, Role::TRAINER, Role::CLIENT] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }
}
