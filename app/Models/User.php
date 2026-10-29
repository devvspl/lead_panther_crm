<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles {
        assignRole as spatieAssignRole;
        syncRoles as spatieSyncRoles;
        hasRole as spatieHasRole;
        scopeRole as spatieScopeRole;
    }

    protected $guarded = [];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function salesTeamMembers(): HasMany
    {
        return $this->hasMany(SalesTeamMember::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'client_id');
    }

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CreditWallet::class, 'client_id');
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'client_id');
    }

    public function rechargeRequests(): HasMany
    {
        return $this->hasMany(RechargeRequest::class, 'client_id');
    }

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
            'password' => 'hashed',
        ];
    }

    public function getIsActiveAttribute(): bool
    {
        return ($this->attributes['status'] ?? 'active') === 'active';
    }

    public function getPrimaryRoleNameAttribute(): string
    {
        return $this->getRoleNames()->first() ?: 'No Role';
    }

    public function getOrganizationNameAttribute(): string
    {
        return $this->organization?->name ?: 'Platform HQ';
    }

    public static function normalizeRoleName(mixed $role): mixed
    {
        if (is_string($role)) {
            $cleaned = strtolower(str_replace(['-', '_'], ' ', $role));
            $target = match ($cleaned) {
                'super admin' => 'Super Admin',
                'builder', 'builder/developer', 'builder developer' => 'Builder/Developer',
                'channel partner' => 'Channel Partner',
                'executive', 'sales executive', 'account manager', 'client' => 'Executive',
                default => $role,
            };

            // If target role exists, always prefer the canonical target
            if (\Spatie\Permission\Models\Role::where('name', $target)->exists()) {
                return $target;
            }

            // Fallback for unmigrated test cases that created legacy names
            if ($target === 'Builder/Developer' && \Spatie\Permission\Models\Role::where('name', 'Builder')->exists()) {
                return 'Builder';
            }
            if ($target === 'Executive' && \Spatie\Permission\Models\Role::where('name', 'Sales Executive')->exists()) {
                return 'Sales Executive';
            }

            if (\Spatie\Permission\Models\Role::where('name', $role)->exists()) {
                return $role;
            }

            return $target;
        }

        return $role;
    }

    public function assignRole(...$roles)
    {
        $normalizedRoles = collect($roles)
            ->flatten()
            ->map(fn ($r) => static::normalizeRoleName($r))
            ->unique()
            ->all();

        return $this->spatieAssignRole($normalizedRoles);
    }

    public function syncRoles(...$roles)
    {
        $normalizedRoles = collect($roles)
            ->flatten()
            ->map(fn ($r) => static::normalizeRoleName($r))
            ->unique()
            ->all();

        return $this->spatieSyncRoles($normalizedRoles);
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        if (is_string($roles) && !str_contains($roles, '|')) {
            $normalized = static::normalizeRoleName($roles);

            return $roles === '*' 
                || $this->roles->contains('name', $roles) 
                || $this->roles->contains('name', $normalized)
                || ($normalized === 'Builder/Developer' && ($this->roles->contains('name', 'Builder') || $this->roles->contains('name', 'Builder/Developer')))
                || ($roles === 'Builder' && $this->roles->contains('name', 'Builder/Developer'))
                || ($normalized === 'Executive' && ($this->roles->contains('name', 'Sales Executive') || $this->roles->contains('name', 'Executive')))
                || ($roles === 'Sales Executive' && $this->roles->contains('name', 'Executive'));
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }

            return false;
        }

        return $this->spatieHasRole($roles, $guard);
    }

    public function scopeRole(\Illuminate\Database\Eloquent\Builder $query, $roles, $guard = null, $without = false): \Illuminate\Database\Eloquent\Builder
    {
        if ($roles instanceof \Illuminate\Support\Collection) {
            $roles = $roles->map(fn ($r) => static::normalizeRoleName($r))->unique()->values()->all();
        } elseif (is_array($roles)) {
            $roles = array_values(array_unique(array_map(fn ($r) => static::normalizeRoleName($r), $roles)));
        } else {
            $roles = static::normalizeRoleName($roles);
        }

        return $this->spatieScopeRole($query, $roles, $guard, $without);
    }
}
