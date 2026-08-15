<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineAssignment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'routine_id',
        'assigned_at',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * assigned_at is cast to datetime (BR-007); is_active is cast to boolean
     * (AR-03).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The client this assignment belongs to (BR-007).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The routine VERSION this assignment points at (BR-007).
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    /**
     * End the assignment (FR-010, AF-006; BR-007, BR-008).
     *
     * Unassignment deactivates the active row; the history row is preserved.
     * Never touches any prescription row (BR-007).
     *
     * @throws DomainException when the assignment is not active
     */
    public function deactivate(): void
    {
        if ($this->is_active !== true) {
            throw new DomainException('Only an active assignment can be deactivated.');
        }

        $this->is_active = false;
        $this->save();
    }
}
