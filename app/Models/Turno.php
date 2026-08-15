<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Turno extends Model
{
    use HasFactory;

    /**
     * The three-state machine (SPEC-006 BR-002, AS-07).
     *
     * These constants are the single source of truth for the turno status
     * values: `active` (bookable), `inactive` (temporarily not bookable,
     * reversible) and `cancelled` (terminal). No other state exists in the
     * MVP.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'capacity_limit',
        'status',
        'label',
    ];

    /**
     * Default attribute values.
     *
     * A new turno is always created with status `active` (FR-001, BR-002,
     * AS-07). The DB column carries the same default; the model default keeps
     * the in-memory record correct for every write path.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * date is cast to Carbon (BR-006). start_time / end_time are NOT cast:
     * a `time` column has no date component, so a datetime cast would be
     * misleading; they stay plain strings (e.g. "08:00") and the interval
     * invariant (end > start on the same date, BR-005) is validated with
     * Laravel's `after` rule on the raw time strings. capacity_limit is cast
     * to integer (BR-007). status remains a plain string validated against
     * the model constants (BR-002).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity_limit' => 'integer',
        ];
    }

    /**
     * Normalize the start time to HH:MM on read (BR-005).
     *
     * The TimePicker is configured without seconds (H:i), but a `time` column
     * has no date component and some write paths (e.g. the factory) store the
     * value with seconds. The accessor keeps the attribute a plain HH:MM
     * string on every read path, so the `date_format:H:i` validation rule
     * holds for edits as well as creates. No datetime cast is used (the value
     * has no date component).
     */
    protected function startTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : substr($value, 0, 5),
        );
    }

    /**
     * Normalize the end time to HH:MM on read (BR-005). See startTime().
     */
    protected function endTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : substr($value, 0, 5),
        );
    }

    /**
     * The active -> inactive transition (FR-005, BR-003).
     *
     * Deactivation makes the turno temporarily not bookable; it can be
     * reactivated (FR-006). The transition is date-independent (BR-003,
     * AS-04): staff may deactivate a turno regardless of whether its date is
     * in the past, present or future.
     *
     * NC-01 (SPEC-007 FR-007, BR-014): deactivating a turno with confirmed
     * bookings auto-cancels every such booking and frees their spots. The
     * status change and the auto-cancel commit or roll back together.
     *
     * @throws DomainException when the turno is not active
     */
    public function deactivate(): void
    {
        if ($this->status !== static::STATUS_ACTIVE) {
            throw new DomainException('Only an active turno can be deactivated.');
        }

        DB::transaction(function (): void {
            $this->status = static::STATUS_INACTIVE;
            $this->save();

            Booking::cancelForTurno($this);
        });
    }

    /**
     * The inactive -> active transition (FR-006, AF-001, BR-003).
     *
     * Reactivation makes the turno bookable again. A `cancelled` turno is
     * terminal and cannot be reactivated (ERR-006, BR-004).
     *
     * @throws DomainException when the turno is not inactive
     */
    public function reactivate(): void
    {
        if ($this->status !== static::STATUS_INACTIVE) {
            throw new DomainException('Only an inactive turno can be reactivated.');
        }

        $this->status = static::STATUS_ACTIVE;
        $this->save();
    }

    /**
     * The active/inactive -> cancelled transition (FR-007, AF-002, BR-003).
     *
     * Cancellation is a terminal transition (BR-004): the turno cannot be
     * edited, reactivated or cancelled again (ERR-006).
     *
     * NC-01 (SPEC-007 FR-007, BR-014): cancelling a turno with confirmed
     * bookings auto-cancels every such booking and frees their spots. The
     * status change and the auto-cancel commit or roll back together.
     *
     * @throws DomainException when the turno is cancelled (or in any other
     *                         state outside active/inactive)
     */
    public function cancel(): void
    {
        if (! in_array($this->status, [static::STATUS_ACTIVE, static::STATUS_INACTIVE], true)) {
            throw new DomainException('Only active or inactive turnos can be cancelled.');
        }

        DB::transaction(function (): void {
            $this->status = static::STATUS_CANCELLED;
            $this->save();

            Booking::cancelForTurno($this);
        });
    }

    /**
     * Whether the turno is currently bookable (FR-008 display; the "currently
     * bookable" notion SPEC-007 will consume).
     */
    public function isActive(): bool
    {
        return $this->status === static::STATUS_ACTIVE;
    }

    /**
     * Whether the turno is inactive (temporarily not bookable, reversible).
     */
    public function isInactive(): bool
    {
        return $this->status === static::STATUS_INACTIVE;
    }

    /**
     * Whether the turno is cancelled (terminal state, BR-004).
     */
    public function isCancelled(): bool
    {
        return $this->status === static::STATUS_CANCELLED;
    }

    /**
     * Scope the query to active (currently bookable) turnos (FR-008).
     *
     * Directly reusable by SPEC-007 when it needs the slots that are bookable.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', static::STATUS_ACTIVE);
    }

    /**
     * The bookings made for this turno (SPEC-007 BR-002, FR-006; the inverse
     * of bookings.turno_id).
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The number of confirmed bookings for this turno (SPEC-007 FR-006,
     * BR-008, BR-014). Only confirmed bookings count toward capacity (BK-11).
     */
    public function confirmedBookingsCount(): int
    {
        return $this->bookings()->confirmed()->count();
    }

    /**
     * Guard against lowering the capacity_limit below the current number of
     * confirmed bookings (SPEC-007 BR-014, ERR-012, NC-01).
     *
     * @throws DomainException when the new limit is below the confirmed count
     */
    public function assertCapacityLimitNotBelowConfirmed(int $newLimit): void
    {
        if ($newLimit < $this->confirmedBookingsCount()) {
            throw new DomainException('The capacity limit cannot be lowered below the number of confirmed bookings.');
        }
    }
}
