<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * The D-16 payment lifecycle statuses (SPEC-005 BR-006).
     *
     * Only `confirmed` is produced by any SPEC-005 flow (BR-005); `pending`
     * and `failed` are reserved for a future external provider (SPEC-014,
     * currently excluded from the backlog) and are never written here.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    /**
     * The two MVP payment methods (SPEC-005 BR-004, D-15 option 1).
     */
    public const METHOD_CASH = 'cash';

    public const METHOD_TRANSFER = 'transfer';

    /**
     * The attributes that are mass assignable.
     *
     * `status` is intentionally NOT fillable: a manually registered payment
     * always persists as `confirmed` via the model default (BR-005). And
     * `recorded_by` is intentionally NOT fillable: it is written by
     * RegisterPayment from auth()->id(), never mass-assigned (PY-06).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cuota_id',
        'amount',
        'method',
        'payment_date',
        'reference',
        'notes',
    ];

    /**
     * Default attribute values.
     *
     * A payment is always created with status `confirmed` in the manual flow
     * (BR-005, D-16 option 1). The DB column carries the same default.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_CONFIRMED,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * The amount is decimal(10,2) cast to a two-decimal string (ADR-003);
     * payment_date is a plain date; status remains a plain string validated
     * against the model constants (BR-006).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    /**
     * The cuota this payment satisfies (BR-007, C-06). The membership is
     * reached through the cuota (`$payment->cuota->membership`), keeping Plan /
     * Membership / Cuota / Payment separate (C-07, BR-010).
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    /**
     * The staff User who recorded this payment (BR-007, PY-06).
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Whether the payment is confirmed (FR-007 display; the only status a
     * SPEC-005 flow produces — BR-005).
     *
     * A confirmed payment is immutable in the MVP (BR-006, PY-05): no
     * status-transition, update or delete method exists on this model.
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}
