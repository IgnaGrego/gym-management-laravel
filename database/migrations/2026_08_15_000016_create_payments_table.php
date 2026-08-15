<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-005 (Payments & Cuotas). A Payment is a financial transaction that
     * satisfies a cuota (BR-007, C-06). cuota_id is a required FK with
     * restrictOnDelete (BR-007): cuotas are never hard-deleted (BR-009), so the
     * historical payment is preserved. amount is decimal(10,2) NOT NULL
     * (BR-007, ADR-003). method is a string column holding cash | transfer
     * (BR-004; string + model constants). payment_date is a NOT NULL date, not
     * in the future (BR-007, PY-03). reference is nullable and required only
     * for transfers (PY-04); notes is optional free text. status is a string
     * column defaulting to 'confirmed' (BR-005, BR-006): manual flows only ever
     * write confirmed; pending/failed are reserved for a future provider
     * (SPEC-014 excluded). recorded_by is a required FK to users with
     * restrictOnDelete (PY-06): the staff User who recorded the payment. The
     * index on payment_date supports the FR-005 date-range filter; the FK
     * columns get their own indexes via constrained().
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuota_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method');
            $table->date('payment_date');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('confirmed');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
