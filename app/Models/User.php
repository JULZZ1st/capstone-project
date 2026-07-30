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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

        return $this->role()->value('name');
    }

    public function roleDisplayName(): string
    {
        if ($this->relationLoaded('role')) {
            return $this->getRelation('role')?->display_name ?? ucfirst((string) $this->roleName());
        }

        return $this->role()->value('display_name') ?? ucfirst((string) $this->roleName());
    }
}
