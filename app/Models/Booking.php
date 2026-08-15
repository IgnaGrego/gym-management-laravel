<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    /**
     * The two-state machine (SPEC-007 BR-003).
     *
     * These constants are the single source of truth for the booking status
     * values: `confirmed` (a valid reservation) and `cancelled` (terminal, the
     * spot is freed). `completed` is RESERVED as the SPEC-008 (Attendance)
     * tie-in and is intentionally NOT a constant or reachable state in this
     * Specification (BK-03, BK-13).
     */
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * status is fillable so the Action and factory can set it explicitly, but
     * the model default is always `confirmed` (BR-003). booked_by is fillable
     * so the Action and factory can set it, but it is never a form field: the
     * Action sets it to the authenticated staff User (BK-12).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'turno_id',
        'status',
        'booked_at',
        'booked_by',
        'notes',
    ];

    /**
     * Default attribute values.
     *
     * A new booking is always created with status `confirmed` (FR-001, BR-003).
     * The DB column carries the same default; the model default keeps the
     * in-memory record correct for every write path.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_CONFIRMED,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * booked_at is cast to Carbon (the gym-local reservation timestamp, no
     * timezone column — the SPEC-006 BR-011 local-time convention). status
     * remains a plain string validated against the model constants (BR-003).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
        ];
    }

    /**
     * The client this booking reserves a spot for (BR-002). The reference is
     * the Client record, not the User account, so clients without a linked
     * User can be booked by staff (BK-01, AF-001).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The turno this booking reserves a spot in (BR-002).
     */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    /**
     * The staff User who created the booking (BK-12). Nullable: null is
     * reserved for a future client self-service path (SPEC-013).
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /**
     * Scope the query to confirmed bookings only (BR-008, BK-11).
     *
     * The predicate reused by the capacity count and the duplicate check.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', static::STATUS_CONFIRMED);
    }

    /**
     * The confirmed -> cancelled transition (FR-004, BR-004).
     *
     * Cancellation is without penalty and terminal: a cancelled booking is
     * never reactivated (BK-06). Only a `confirmed` booking can be cancelled
     * (ERR-009).
     *
     * @throws DomainException when the booking is not confirmed
     */
    public function cancel(): void
    {
        if ($this->status !== static::STATUS_CONFIRMED) {
            throw new DomainException('Only a confirmed booking can be cancelled.');
        }

        $this->status = static::STATUS_CANCELLED;
        $this->save();
    }

    /**
     * Bulk auto-cancel every confirmed booking of a turno (FR-007, BR-014,
     * NC-01).
     *
     * Called from the turno lifecycle methods (deactivate/cancel) inside their
     * transaction, so the status change and the auto-cancel commit or roll
     * back together. Idempotent: already-cancelled rows are untouched. Returns
     * the number of affected bookings.
     */
    public static function cancelForTurno(Turno $turno): int
    {
        return static::query()
            ->where('turno_id', $turno->id)
            ->confirmed()
            ->update(['status' => static::STATUS_CANCELLED]);
    }

    /**
     * The number of confirmed bookings for a turno (BR-008, BK-11).
     *
     * Only confirmed bookings count toward capacity; cancelled bookings do not
     * (spots reopen per D-08).
     */
    public static function confirmedCountForTurno(int $turnoId): int
    {
        return static::query()
            ->where('turno_id', $turnoId)
            ->confirmed()
            ->count();
    }
}
