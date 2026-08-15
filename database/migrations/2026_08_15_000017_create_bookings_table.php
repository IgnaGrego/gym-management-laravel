<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-007 (Bookings). A Booking is a reservation made by a client for a
     * turno (BR-001, BR-002; D-07 option 1). client_id and turno_id are
     * required FKs (BR-002) with restrictOnDelete as a defensive guard
     * consistent with the preservation pattern: clients, users and turnos are
     * never hard-deleted, and a deletion attempt should be blocked rather than
     * cascade into historical booking data (BR-011). status is a string column
     * (not a DB enum) defaulting to 'confirmed' (BR-003) with the model
     * constants as the single source of truth (ADR-004 precedent). booked_at is
     * a NOT NULL timestamp with no DB default: the Action always supplies now()
     * (FR-001, "when the reservation was made", BK-12). booked_by is a nullable
     * FK to users (BK-12 audit; null reserved for SPEC-013 self-service).
     * notes is optional free text (max 500 is a technical detail, SPEC-007
     * §10). The composite index on (turno_id, status) supports the BR-008
     * capacity count (WHERE turno_id = ? AND status = 'confirmed') and booking
     * lists by turno (FR-002); client_id is indexed automatically via its FK.
     *
     * The partial unique index (BR-009) is the single intentional raw-SQL
     * statement: Laravel's schema builder has no partial-index API, and
     * PostgreSQL (and SQLite) support partial indexes natively. It enforces "at
     * most one confirmed booking per (client_id, turno_id)" at the database
     * level while still allowing any number of cancelled rows for the same pair
     * (AF-003). See ADR-006.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('turno_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('confirmed');
            $table->timestamp('booked_at');
            $table->foreignId('booked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['turno_id', 'status']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX bookings_confirmed_client_turno_unique ON bookings (client_id, turno_id) WHERE status = 'confirmed'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
