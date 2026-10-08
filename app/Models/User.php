<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'must_change_password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function createdContributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'created_by');
    }

    public function verifiedContributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'verified_by');
    }

    public function securityProfile(): HasOne
    {
        return $this->hasOne(UserSecurityProfile::class);
    }

    public function securityPermissions(): BelongsToMany
    {
        return $this->belongsToMany(SecurityPermission::class)
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    public function securityActivityEvents(): HasMany
    {
        return $this->hasMany(SecurityActivityEvent::class);
    }

    public function actedSecurityActivityEvents(): HasMany
    {
        return $this->hasMany(SecurityActivityEvent::class, 'actor_id');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return in_array($this->roleName(), $roles, true);
    }

    public function roleName(): ?string
    {
        if ($this->relationLoaded('role')) {
            return $this->getRelation('role')?->name;
        }

        $name = $this->role()->value('name');

        if ($name !== null) {
            return $name;
        }

        // Fallback for legacy schema where `users` may still have a string `role` column
        // (kept during migrations). This preserves access checks for existing seeded
        // or migrated users until `role_id` is populated.
        return $this->getAttribute('role');
    }

    public function roleDisplayName(): string
    {
        if ($this->relationLoaded('role')) {
            return $this->getRelation('role')?->display_name ?? ucfirst((string) $this->roleName());
        }

        $display = $this->role()->value('display_name');

        if ($display !== null) {
            return $display;
        }

        // If no Role relation exists or `role_id` is not set, try to resolve a display
        // name from the configured defaults for backward compatibility.
        $name = $this->roleName();

        if ($name && is_array(config('auth_defaults.roles')) && isset(config('auth_defaults.roles')[$name])) {
            return config('auth_defaults.roles')[$name]['display_name'] ?? ucfirst((string) $name);
        }

        return ucfirst((string) $name);
    }
}
