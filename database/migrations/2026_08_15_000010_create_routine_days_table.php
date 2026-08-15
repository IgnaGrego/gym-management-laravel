<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-010 (Routines). An ordinal day within a repeating cycle — Day 1..N
     * (gate D-10 option 2, pre-approved; BR-003). routine_id is a required FK
     * to routines.id with restrictOnDelete as a defensive guard: a routine
     * version is never deleted (archiving instead, BR-008) and a deletion
     * attempt should be blocked rather than cascade into prescription data.
     * day_number is an unsigned integer (BR-010); the UNIQUE index on
     * (routine_id, day_number) enforces ERR-002 (duplicate day numbers in a
     * version) at the database level (spec-requested, SPEC-010 §10). Gaps in
     * day numbering are permitted (AR-07); no CHECK constraint is added
     * (framework-validation-first convention, ADR-003).
     */
    public function up(): void
    {
        Schema::create('routine_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('day_number');
            $table->timestamps();

            $table->unique(['routine_id', 'day_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routine_days');
    }
};
