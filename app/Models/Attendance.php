<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * recorded_by is fillable so the create path (mutateFormDataBeforeCreate),
     * the factory and direct writes work, but it is never a form field: it is
     * set to the authenticated staff User at creation (BR-011, AT-08).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'attended_at',
        'recorded_by',
        'turno_id',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * attended_at is cast to Carbon (BR-007 — the gym-local access timestamp;
     * no timezone column, same local-time convention as SPEC-006 BR-011). The
     * FK columns stay plain integers and notes a plain string.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attended_at' => 'datetime',
        ];
    }

    /**
     * The client this attendance record belongs to (BR-002, FR-001).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The staff User who recorded the check-in (BR-011, FR-006, AT-08).
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The optional gym-access slot (turno) the client attended (AT-06,
     * BR-012). The link is optional metadata; no turno status, time or
     * capacity semantics apply to it in this Specification.
     */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    /**
     * Scope the query to one client's attendance records (FR-004).
     *
     * Ordering by attended_at is applied by the consuming UI (chronological
     * history, AC-11).
     */
    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }
}
