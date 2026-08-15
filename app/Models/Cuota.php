<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuota extends Model
{
    use HasFactory;

    /**
     * The three-state machine (SPEC-005 BR-003).
     *
     * These constants are the single source of truth for the cuota status
     * values: `pending` (awaiting its single full payment), `paid` (satisfied
     * by a confirmed payment) and `cancelled` (the NC-04 consequence for a
     * pending cuota of a cancelled membership — uncollectible). No other state
     * exists in the MVP; in particular there is no overdue/late state (BR-003).
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * Status is normally set through markPaid()/cancel(); it stays fillable so
     * the factory and the Membership created hook can create the record with
     * the default `pending` value (BR-003, FR-001).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'membership_id',
        'amount',
        'status',
        'paid_at',
    ];

    /**
     * Default attribute values.
     *
     * A new cuota is always created with status `pending` (BR-003, FR-001).
     * The DB column carries the same default; the model default keeps the
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
     * The amount is decimal(10,2) and cast to a two-decimal string (ADR-003);
     * paid_at is a nullable timestamp; status remains a plain string validated
     * against the model constants (BR-003).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The membership this cuota belongs to (BR-001, FR-001).
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * The payments recorded against this cuota (BR-007, FR-003 payment
     * history), ordered by payment_date.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('payment_date');
    }

    /**
     * The pending -> paid transition (FR-006, BR-003, NC-01, BR-014).
     *
     * Invoked by RegisterPayment once the single matching confirmed payment is
     * persisted. Sets status `paid` and stamps paid_at.
     *
     * @throws DomainException when the cuota is not pending
     */
    public function markPaid(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new DomainException('Only a pending cuota can be marked as paid.');
        }

        $this->status = self::STATUS_PAID;
        $this->paid_at = now();
        $this->save();
    }

    /**
     * The pending -> cancelled transition (BR-003, BR-015, NC-04).
     *
     * Called by Membership::cancel() for a still-pending cuota; the cuota
     * becomes uncollectible and is no longer payable (ERR-011).
     *
     * @throws DomainException when the cuota is not pending
     */
    public function cancel(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new DomainException('Only a pending cuota can be cancelled.');
        }

        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    /**
     * Edit the amount of a pending cuota (FR-002, D-02 option 2, BR-012).
     *
     * Positivity is enforced by the Filament form (ERR-006); this method only
     * enforces the pending-only rule (ERR-007).
     *
     * @throws DomainException when the cuota is not pending
     */
    public function updateAmount(string $amount): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new DomainException('Only a pending cuota amount can be edited.');
        }

        $this->amount = $amount;
        $this->save();
    }

    /**
     * Whether the cuota is pending (payable) — FR-007 display.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Whether the cuota is paid — FR-007 display.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Whether the cuota is cancelled (uncollectible) — FR-007 display.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Scope the query to pending (payable) cuotas (FR-004).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
