<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Routine extends Model
{
    use HasFactory;

    /**
     * The three-state version lifecycle (SPEC-010 BR-002, AR-01).
     *
     * These constants are the single source of truth for the routine version
     * status values: `draft` (a new routine; may be empty), `active` (the only
     * assignable status) and `archived` (terminal, reached only when a new
     * version supersedes this one). No other state exists in the MVP
     * (ADR-004 string-with-constants convention, precedent: Turno::STATUS_*).
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Routine-status display labels (presentation only; SPEC-016 FR-006,
     * ADR-009). Keyed by the stored identifier; the persisted value is never
     * changed.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            static::STATUS_DRAFT => 'Borrador',
            static::STATUS_ACTIVE => 'Activo',
            static::STATUS_ARCHIVED => 'Archivado',
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'status',
        'version_number',
        'replaces_id',
        'created_by',
    ];

    /**
     * Default attribute values.
     *
     * A new routine is always created as version 1 with status `draft`
     * (FR-001, BR-002, AR-01, AR-02). The DB columns carry the same defaults;
     * the model defaults keep the in-memory record correct for every write
     * path (the Turno / Membership precedent).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'version_number' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * version_number is cast to integer (BR-001, AR-02; the capacity_limit
     * precedent). status remains a plain string validated against the model
     * constants (ADR-004).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
        ];
    }

    /**
     * The ordinal days of this routine version, ordered by day number
     * (D-10 option 2, BR-003; FR-003 display — the same ordering discipline
     * as Client::memberships()).
     */
    public function days(): HasMany
    {
        return $this->hasMany(RoutineDay::class)->orderBy('day_number');
    }

    /**
     * The version this one replaces (self-referential lineage chain, AR-02);
     * null for version 1 (BR-001).
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(static::class, 'replaces_id');
    }

    /**
     * The version(s) that replace this one (self-referential inverse, AR-02).
     * Used to find the lineage head and to walk the lineage forward.
     */
    public function replacedBy(): HasMany
    {
        return $this->hasMany(static::class, 'replaces_id');
    }

    /**
     * The User who created this version (BR-011, AR-08); informational/audit
     * only — management is role-based, not ownership-based.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The assignment records pointing at this routine version (FR-011;
     * SPEC-011 will consume the active assignment).
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoutineAssignment::class);
    }

    /**
     * Whether the version is a draft (BR-002).
     */
    public function isDraft(): bool
    {
        return $this->status === static::STATUS_DRAFT;
    }

    /**
     * Whether the version is active — the only assignable status (BR-002,
     * BR-007, AR-03).
     */
    public function isActive(): bool
    {
        return $this->status === static::STATUS_ACTIVE;
    }

    /**
     * Whether the version is archived — terminal and read-only (BR-002).
     */
    public function isArchived(): bool
    {
        return $this->status === static::STATUS_ARCHIVED;
    }

    /**
     * The draft -> active transition (FR-007, BR-002).
     *
     * Activation requires the version to have at least one day (ERR-003) and
     * each day at least one set row (ERR-004). The content invariants depend
     * on the persisted days/rows, not on form state, so they are checked here
     * as the single enforcement point (the Turno / Membership model-method
     * precedent).
     *
     * @throws DomainException when the version is not a draft, has zero days
     *                         or has a day with zero set rows
     */
    public function activate(): void
    {
        if ($this->status !== static::STATUS_DRAFT) {
            throw new DomainException('Solo una rutina en borrador puede activarse.');
        }

        if ($this->days()->count() === 0) {
            throw new DomainException('Una rutina debe tener al menos un día para activarse.');
        }

        if ($this->days()->doesntHave('exercises')->exists()) {
            throw new DomainException('Cada día de la rutina debe tener al menos una serie para activarse.');
        }

        $this->status = static::STATUS_ACTIVE;
        $this->save();
    }

    /**
     * The ids of every version of this routine's lineage (BR-001, AR-02;
     * FR-004 version history).
     *
     * Walks the `replaces` chain backwards to the lineage root and then the
     * `replacedBy` chain forward, collecting every version id. Lineages are
     * short chains; a PHP walk is the simplest correct mechanism (no
     * recursive SQL; ADR-003 framework-first).
     *
     * @return array<int, int>
     */
    public function lineageIds(): array
    {
        $root = $this;

        while ($root->replaces instanceof Routine) {
            $root = $root->replaces;
        }

        $ids = [];
        $current = $root;

        while ($current instanceof Routine) {
            $ids[] = $current->id;
            $current = $current->replacedBy()->orderBy('version_number')->first();
        }

        return $ids;
    }

    /**
     * Every version of this routine's lineage, ordered by version number
     * (FR-004).
     */
    public function lineage(): Collection
    {
        return static::query()
            ->whereKey($this->lineageIds())
            ->orderBy('version_number')
            ->get();
    }

    /**
     * Scope the query to active (currently assignable) versions (BR-002,
     * BR-007; the "currently assignable" set for consumers — the
     * Turno::scopeActive / Exercise::scopeActive pattern).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', static::STATUS_ACTIVE);
    }

    /**
     * Scope the query to the current/latest version of each lineage (FR-002
     * list: one row per lineage).
     *
     * A routine that no other version replaces is the lineage head.
     */
    public function scopeLineageHeads(Builder $query): Builder
    {
        return $query->whereDoesntHave('replacedBy');
    }
}
