<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-002 (Client Management). Client records are standalone: full_name
     * and dni are required; contact and health fields are optional (FR-001,
     * AD-01). The nullable unique user_id holds the optional 1:1 Client <-> User
     * link established only by explicit provisioning (BR-003, ADR-002); the
     * unique index enforces the 1:1 rule in both directions. nullOnDelete is a
     * defensive safety net only: users are never hard-deleted in the MVP
     * (SPEC-001 BR-007) and a Client record must survive if a linked User were
     * ever removed (BR-008).
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('dni')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->text('injuries_notes')->nullable();
            $table->text('medical_conditions_notes')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
