<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-012 (Public Registration). The clients table gains the
     * registration-lifecycle status column (AS-01, BR-004): a plain NOT NULL
     * string with the three flow values `pending` / `active` / `rejected`,
     * defaulting to `active`. The default preserves the behavior of existing
     * and staff-created clients: only public registration writes `pending`
     * (AC-13). No DB enum and no CHECK constraint: the project convention is
     * framework validation plus model constants (ADR-003; the string-with-
     * constants precedent of memberships/turnos/routines, ADR-004). The
     * column default matches the Client model default attribute.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('status')->default('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
