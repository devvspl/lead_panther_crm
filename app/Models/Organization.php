<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Organization extends Model
{
    protected $guarded = [];

    public function clients(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function projects(): HasManyThrough
    {
        return $this->hasManyThrough(Project::class, User::class, 'organization_id', 'client_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getIsActiveAttribute(): bool
    {
        return ($this->attributes['status'] ?? 'active') === 'active';
    }

    /**
     * Mutator to safely ignore legacy 'type' assignment since column was dropped.
     */
    public function setTypeAttribute($value): void
    {
        // Ignored: Organization Type has been removed from organizations table.
    }

    /**
     * Accessor for legacy code expecting 'type'.
     */
    public function getTypeAttribute(): ?string
    {
        return $this->attributes['type'] ?? null;
    }
}
