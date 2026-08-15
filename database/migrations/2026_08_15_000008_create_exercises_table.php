<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-009 (Exercise Catalogue). An exercise is a single exercise that can
     * be included in routines (domain-model §Exercise; confirmed decision
     * C-09), with the attributes approved by gate D-20 option 2: a required
     * unique name (BR-003, ERR-002), a required muscle group (BR-002,
     * BR-004), and optional equipment (EX-05), difficulty (BR-005),
     * instructions (EX-10) and video URL (BR-006). The fixed sets
     * (muscle_group, difficulty) are plain string columns validated against
     * the model constants — not PostgreSQL enums and not PHP enums — per the
     * validation-first, string-with-constants convention (ADR-003, ADR-004;
     * precedent: turnos.status). is_active is a boolean defaulting to true
     * (BR-007, EX-07), the same representation as plans.is_active (SPEC-003
     * AP-02). The unique index on name enforces BR-003 at the database level,
     * including inactive exercises (AF-005). Indexes on muscle_group and
     * is_active support the FR-002 filters. There are no foreign keys and no
     * relationships: the exercise catalogue is standalone in this
     * Specification (BR-011); SPEC-010 will add routine_exercises.exercise_id
     * from the consuming module. No seeder is required (EX-09): exercises are
     * created by staff in the admin panel only. No hard deletion (BR-008): no
     * delete operation exists; deactivation is used instead.
     */
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->string('muscle_group', 50);
            $table->string('equipment', 255)->nullable();
            $table->string('difficulty', 20)->nullable();
            $table->text('instructions')->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('muscle_group');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
