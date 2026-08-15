<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * Fixed role catalog (SPEC-001 FR-004, BR-001; ADR-001).
     *
     * These constants are the single source of truth for the role names.
     */
    public const ADMIN = 'ADMIN';

    public const TRAINER = 'TRAINER';

    public const CLIENT = 'CLIENT';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * The users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
