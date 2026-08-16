<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Client extends Model
{
    use HasFactory;

    /**
     * Access-gate denial reasons (SPEC-008 BR-003, FR-005, ERR-003/ERR-004).
     *
     * These constants are the single source of truth for the access decision
     * returned by accessDenialReason(): `null` when the client qualifies,
     * otherwise exactly one of these reasons.
     */
    public const ACCESS_DENIED_NO_MEMBERSHIP = 'no_membership';

    public const ACCESS_DENIED_MEMBERSHIP_EXPIRED = 'membership_expired';

    public const ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP = 'no_active_membership';

    /**
     * The three registration-flow status values (SPEC-012 BR-004, AS-01).
     *
     * These constants are the single source of truth for the client status:
     * `pending` (created by public registration, awaiting staff approval),
     * `active` (the normal operating state; the default for staff-created and
     * pre-existing clients) and `rejected` (terminal). No other lifecycle
     * state exists in the MVP (SPEC-002 OQ-03 stays open for inactive/blocked).
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Client-status display labels (presentation only; SPEC-016 FR-006,
     * ADR-009). Keyed by the stored identifier; the persisted value is never
     * changed.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            static::STATUS_PENDING => 'Pendiente',
            static::STATUS_ACTIVE => 'Activo',
            static::STATUS_REJECTED => 'Rechazado',
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * user_id is intentionally NOT fillable: the optional 1:1 link to a User
     * is written only by the provisioning Action via user()->associate()
     * (SPEC-002 BR-002, BR-003; architecture SPEC-002 §5).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'dni',
        'email',
        'phone',
        'emergency_contact',
        'injuries_notes',
        'medical_conditions_notes',
        'status',
    ];

    /**
     * Default attribute values.
     *
     * A new client is always created with status `active` (SPEC-012 BR-004,
     * AS-01): only public registration writes `pending`. The DB column
     * carries the same default; the model default keeps the in-memory record
     * correct for every write path (AC-13).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * The linked User account, when one has been provisioned (SPEC-002 FR-005,
     * BR-003). A Client may have at most one linked account; the link is
     * optional and can be created later (BR-001).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether a linked User account exists (SPEC-002 FR-006).
     */
    public function hasLinkedUser(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Whether the client is a pending registration awaiting staff approval
     * (SPEC-012 FR-003, AS-01).
     */
    public function isPending(): bool
    {
        return $this->status === static::STATUS_PENDING;
    }

    /**
     * Whether the client is active — the normal operating state (SPEC-012
     * BR-004). Staff-created and pre-existing clients are active by default.
     */
    public function isActive(): bool
    {
        return $this->status === static::STATUS_ACTIVE;
    }

    /**
     * Whether the client was rejected — a terminal state (SPEC-012 BR-006).
     */
    public function isRejected(): bool
    {
        return $this->status === static::STATUS_REJECTED;
    }

    /**
     * Scope the query to pending registrations (SPEC-012 FR-003, AS-01).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', static::STATUS_PENDING);
    }

    /**
     * Approve a pending registration (SPEC-012 FR-004, BR-005).
     *
     * `pending -> active` and, when a linked User exists, activates it
     * (`is_active` false -> true) so the applicant can log in (AS-03). Both
     * writes happen in one transaction (FR-004). Approval is only possible
     * from `pending` (ERR-007): `active` is the normal operating state and
     * `rejected` is terminal.
     *
     * @throws DomainException when the client is not pending
     */
    public function approve(): void
    {
        if ($this->status !== static::STATUS_PENDING) {
            throw new DomainException('Solo un cliente pendiente puede ser aprobado.');
        }

        DB::transaction(function (): void {
            $this->status = static::STATUS_ACTIVE;
            $this->save();

            if ($this->user !== null) {
                $this->user->is_active = true;
                $this->user->save();
            }
        });
    }

    /**
     * Reject a pending registration (SPEC-012 FR-005, BR-005, BR-006).
     *
     * `pending -> rejected` (terminal). The linked User is left untouched and
     * stays deactivated, so the applicant cannot log in (AS-05).
     *
     * @throws DomainException when the client is not pending
     */
    public function reject(): void
    {
        if ($this->status !== static::STATUS_PENDING) {
            throw new DomainException('Solo un cliente pendiente puede ser rechazado.');
        }

        $this->status = static::STATUS_REJECTED;
        $this->save();
    }

    /**
     * The membership records of this client (SPEC-004 FR-004, C-08).
     *
     * A client may hold more than one membership at the same time, including
     * several active ones (SPEC-004 BR-010). Chronological display ordering
     * by start_date is applied by the MembershipsRelationManager.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * The attendance records of this client (SPEC-008 C-02, FR-004).
     *
     * Display ordering by attended_at is applied by the consuming UI
     * (chronological history, AC-11).
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * The booking records of this client (SPEC-007 C-02, FR-002).
     *
     * A client aggregates bookings; the inverse of bookings.client_id. The
     * attendances() pattern.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The routine-assignment history of this client, ordered by assigned_at
     * (SPEC-010 FR-011, BR-007; the memberships()/attendances() pattern).
     *
     * A client has at most one ACTIVE assignment at a time (AR-03); the full
     * history is preserved (BR-008).
     */
    public function routineAssignments(): HasMany
    {
        return $this->hasMany(RoutineAssignment::class)->orderBy('assigned_at');
    }

    /**
     * The workout logs of this client (SPEC-011 C-02, FR-003).
     *
     * One row per performed set (D-11 option 2, BR-001). Display ordering by
     * performed_at is applied by the consuming UI (chronological history,
     * AC-8 — the attendances() pattern).
     */
    public function workoutLogs(): HasMany
    {
        return $this->hasMany(WorkoutLog::class);
    }

    /**
     * Whether the client has an assignment to the given routine VERSION —
     * active or historical (SPEC-011 BR-004, BR-008, ERR-003, WL-07).
     *
     * The ERR-003 assigned-version validation helper: assignment history is
     * preserved per SPEC-010 BR-008/AR-09. Because AssignRoutine only ever
     * assigns `active` versions (SPEC-010 ERR-008), a version with any
     * assignment row is never a `draft` — so this predicate automatically
     * excludes draft rows.
     */
    public function hasRoutineAssignmentTo(int $routineId): bool
    {
        return $this->routineAssignments()->where('routine_id', $routineId)->exists();
    }

    /**
     * The routine version currently assigned to this client (SPEC-010 FR-011,
     * AR-03), or null when the client has no active assignment.
     */
    public function currentRoutine(): ?Routine
    {
        return $this->routineAssignments()
            ->where('is_active', true)
            ->first()
            ?->routine;
    }

    /**
     * Whether the client currently qualifies for gym access (SPEC-008 BR-003,
     * D-05 option 1).
     *
     * At least one membership with status `active` AND end_date >= today
     * suffices (D-06 option 2: no "primary membership" selection). No grace
     * period after expiry. Evaluated at check-in time only (BR-004).
     */
    public function hasQualifyingMembership(): bool
    {
        return $this->memberships()->qualifying()->exists();
    }

    /**
     * The access decision and its reason for this client (SPEC-008 FR-005,
     * ERR-003/ERR-004).
     *
     * Returns null when the client qualifies (hasQualifyingMembership), or
     * one of the ACCESS_DENIED_* constants otherwise. Evaluation order:
     * (1) no membership records -> ACCESS_DENIED_NO_MEMBERSHIP;
     * (2) a qualifying membership exists -> null (qualified);
     * (3) an `active` membership whose end date has passed exists ->
     *     ACCESS_DENIED_MEMBERSHIP_EXPIRED (the memberships:expire command
     *     window, SPEC-004 BR-007 / ADR-004);
     * (4) otherwise -> ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP.
     */
    public function accessDenialReason(): ?string
    {
        if (! $this->memberships()->exists()) {
            return static::ACCESS_DENIED_NO_MEMBERSHIP;
        }

        if ($this->hasQualifyingMembership()) {
            return null;
        }

        if ($this->memberships()
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereDate('end_date', '<', today())
            ->exists()) {
            return static::ACCESS_DENIED_MEMBERSHIP_EXPIRED;
        }

        return static::ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP;
    }
}
