<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The roles assigned to this user (SPEC-001 FR-005).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * The Client record linked to this user, when one has been provisioned
     * (SPEC-002 FR-005, BR-003). The link lives on the clients table
     * (clients.user_id, nullable unique FK; ADR-002) and is optional.
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    /**
     * The id of the Client record linked to this user, or null when unlinked
     * (SPEC-013 BR-002, C-13; ADR-007).
     *
     * The single source of truth for the ownership comparison used by the
     * CLIENT-own policy extensions (Client / Membership / Cuota / Payment /
     * Attendance / Booking / WorkoutLog / Routine): a CLIENT may access only
     * the record whose owning client equals this id.
     */
    public function clientId(): ?int
    {
        return $this->client?->id;
    }

    /**
     * Whether the user holds the given role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Whether the user holds at least one of the given roles.
     *
     * A user holding several roles receives the union of the permissions
     * granted by those roles (SPEC-001 BR-002).
     *
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Whether the user may access the given Filament panel.
     *
     * The admin panel is accessible only to authenticated users holding ADMIN
     * or TRAINER (SPEC-001 FR-006, BR-004).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }
}
