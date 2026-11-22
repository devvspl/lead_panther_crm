<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Organization extends Model
{
    use \App\Traits\HasLeadCreditCost;
    protected $guarded = [];
    protected $casts = [
        'min_credit_threshold' => 'integer',
        'show_builder_credit_balance' => 'boolean',
        'internal_processing_enabled' => 'boolean',
        'internal_processing_config' => 'array',
        'assignment_config' => 'array',
        'auto_assign_enabled' => 'boolean',
        'lead_display_config' => 'array',
    ];

    /**
     * Default lead details drawer display preferences.
     */
    public static function defaultLeadDisplayConfig(): array
    {
        return [
            'phone' => true,
            'email' => true,
            'city' => true,
            'project' => true,
            'budget' => true,
            'property_type' => true,
            'requirement' => true,
            'meta_submission' => true,
            'meta_context' => true,
        ];
    }

    /**
     * Get effective lead display config for this organization.
     */
    public function getLeadDisplayConfig(): array
    {
        $defaults = static::defaultLeadDisplayConfig();
        $stored = $this->lead_display_config;

        if (! is_array($stored) || empty($stored)) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }

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
