<?php

namespace App\Models;

use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Membership extends Model
{
    use HasFactory;

    /**
     * The four-state machine (SPEC-004 BR-004, AM-03).
     *
     * These constants are the single source of truth for the membership
     * status values; no other state exists in the MVP (no freeze/suspended).
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Membership-status display labels (presentation only; SPEC-016 FR-006,
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
            static::STATUS_EXPIRED => 'Vencida',
            static::STATUS_CANCELLED => 'Cancelada',
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * end_date is fillable so the creating hook and explicit factory values
     * work, but the standard create/renew paths never supply it (BR-003).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'plan_id',
        'start_date',
        'end_date',
        'duration_days',
        'status',
    ];

    /**
     * Default attribute values.
     *
     * A new membership is always created with status `pending` (BR-005). The
     * DB column carries the same default; the model default keeps the
     * in-memory record correct for every write path.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * Dates are cast to Carbon; the status remains a plain string validated
     * against the model constants (BR-004).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_days' => 'integer',
        ];
    }

    /**
     * Compute the period invariant on creation (BR-003, AM-07).
     *
     * end_date = start_date + duration_days - 1 (the membership is valid for
     * duration_days calendar days, inclusive). The hook is the single source
     * of truth across the create form, renewal, the factory and future
     * SPEC-005 write paths; an explicitly supplied end_date is never
     * overwritten.
     */
    protected static function booted(): void
    {
        static::creating(function (Membership $membership): void {
            if ($membership->end_date === null && $membership->start_date !== null && $membership->duration_days !== null) {
                $membership->end_date = static::computeEndDate($membership->start_date, $membership->duration_days);
            }
        });

        static::created(function (Membership $membership): void {
            // SPEC-005 FR-001, BR-001, BR-002, BR-003, ADR-005: the system
            // auto-generates exactly one cuota per membership, defaulting to
            // the plan's price at generation time and status `pending`. The
            // hook is the single source of truth across the create form, the
            // renewal Action, the factory and any future write path, and runs
            // synchronously inside whichever transaction is active.
            //
            // The price is read through the relationship query builder (not
            // `$membership->plan->price`) so the plan relationship is NOT
            // cached on the instance — a later `$membership->plan` access must
            // always reflect the current plan row.
            $membership->cuota()->create([
                'amount' => $membership->plan()->value('price'),
                'status' => Cuota::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Compute the end date for a period of the given duration from a start
     * date (BR-003, AM-07).
     */
    public static function computeEndDate(Carbon|string $startDate, int $durationDays): Carbon
    {
        return Carbon::parse($startDate)->addDays($durationDays - 1);
    }

    /**
     * The client this membership belongs to (BR-002, FR-003).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The plan this membership enrolls the client in (BR-002, FR-003).
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The single cuota generated for this membership (SPEC-005 BR-001, NC-02;
     * FR-001). Exactly one cuota per membership — the `membership_id` UNIQUE
     * constraint enforces it at the database level and the `created` hook
     * guarantees it in practice (ADR-005).
     */
    public function cuota(): HasOne
    {
        return $this->hasOne(Cuota::class);
    }

    /**
     * The pending -> active transition — the FR-008 contract for SPEC-005.
     *
     * Invoked only when the first cuota of the membership is confirmed as
     * paid (BR-006, AM-05). Enforces: the membership is `pending` (AC-15) and
     * the end date has not passed (AC-15; only while within the validity
     * period). No authorization check: this is a system-internal transition
     * called by the SPEC-005 payment-confirmation path, never by a user
     * request (SPEC-004 §9).
     *
     * @throws DomainException when the state rule is violated
     */
    public function activate(): void
    {
        if ($this->status !== static::STATUS_PENDING) {
            throw new DomainException('Solo una membresía pendiente puede activarse.');
        }

        if ($this->end_date < Carbon::today()) {
            throw new DomainException('Una membresía no puede activarse después de que haya pasado su fecha de fin.');
        }

        $this->status = static::STATUS_ACTIVE;
        $this->save();
    }

    /**
     * Cancel a pending or active membership (FR-006, BR-008).
     *
     * Cancellation is a manual, terminal transition (BR-009): the membership
     * is never reported active again even if its end date has not passed.
     *
     * @throws DomainException when the status is expired or cancelled (ERR-004)
     */
    public function cancel(): void
    {
        if (! in_array($this->status, [static::STATUS_PENDING, static::STATUS_ACTIVE], true)) {
            throw new DomainException('Solo las membresías pendientes o activas pueden cancelarse.');
        }

        DB::transaction(function (): void {
            $this->status = static::STATUS_CANCELLED;
            $this->save();

            // SPEC-005 BR-015, NC-04: cancelling a membership transitions its
            // still-pending cuota to `cancelled` (uncollectible). A paid cuota
            // is never touched.
            $cuota = $this->cuota;

            if ($cuota !== null && $cuota->status === Cuota::STATUS_PENDING) {
                $cuota->cancel();
            }
        });
    }

    /**
     * Whether the membership is currently active (FR-007; consumed later by
     * SPEC-007/008 under D-05, BR-016).
     */
    public function isActive(): bool
    {
        return $this->status === static::STATUS_ACTIVE;
    }

    /**
     * Whether the membership is expired (terminal state, BR-009).
     */
    public function isExpired(): bool
    {
        return $this->status === static::STATUS_EXPIRED;
    }

    /**
     * Scope the query to qualifying memberships (SPEC-008 BR-003, D-05
     * option 1; the same rule SPEC-007 BR-005 consumes when unblocked).
     *
     * A membership qualifies for gym access when its status is `active` AND
     * its end date has not passed (end_date >= today). The end_date check is
     * defensive against the memberships:expire command window (SPEC-004
     * BR-007 / ADR-004): no membership is ever reported qualifying after its
     * end date, with no grace period (D-05 option 3 not chosen). Multiple
     * concurrent active memberships (D-06 option 2): at least one qualifying
     * membership suffices, so consumers use exists() semantics.
     *
     * The predicate is `end_date >= today` per SPEC-008 §5 / architecture
     * SPEC-008 §5; the whereDate comparison is used so the day boundary binds
     * correctly on every supported database (a Carbon binding would be
     * formatted with time-of-day and break the inclusive last-day check).
     */
    public function scopeQualifying(Builder $query): Builder
    {
        return $query
            ->where('status', static::STATUS_ACTIVE)
            ->whereDate('end_date', '>=', today());
    }
}
