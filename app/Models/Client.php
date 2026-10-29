<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Client extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function ($client) {
            if (empty($client->email)) {
                $slug = Str::slug($client->name ?: 'client');
                $client->email = $slug . '_' . uniqid() . '@client.local';
            }
            if (empty($client->password)) {
                $client->password = bcrypt(Str::random(16));
            }
            if (empty($client->status)) {
                $client->status = 'active';
            }
        });

        static::created(function ($client) {
            if ($client->roles()->count() === 0) {
                try {
                    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Builder/Developer', 'guard_name' => 'web']);
                    $client->assignRole($role);
                } catch (\Throwable $e) {}
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CreditWallet::class, 'client_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'client_id');
    }

    public function users()
    {
        // For backwards compatibility: return users belonging to the same organization
        return $this->hasMany(User::class, 'organization_id', 'organization_id');
    }

    public function getBillingEmailAttribute(): ?string
    {
        return $this->email;
    }

    public function setBillingEmailAttribute($value): void
    {
        if (!empty($value)) {
            $this->attributes['email'] = $value;
        }
    }
}
