<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineDay extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'routine_id',
        'day_number',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * day_number is cast to integer (BR-003, BR-010).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
        ];
    }

    /**
     * The routine version this ordinal day belongs to (BR-003).
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    /**
     * The set-level prescription rows of this day, ordered by set number
     * (D-11 option 2, BR-004; FR-003 display).
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(RoutineExercise::class)->orderBy('set_number');
    }
}
