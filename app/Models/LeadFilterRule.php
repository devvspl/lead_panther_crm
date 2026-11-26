<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadFilterRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'campaign_id',
        'name',
        'description',
        'action', // 'include' or 'exclude'
        'group_logic', // 'AND' or 'OR' between groups
        'conditions',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conditions' => 'array',
        'priority' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LeadFilterLog::class, 'rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('organization_id', $orgId);
    }

    /**
     * Generate a concise human-readable explanation of this rule.
     */
    public function humanSummary(): string
    {
        $actionText = $this->action === 'exclude' ? 'Exclude Lead' : 'Include Lead';
        $groups = $this->conditions['groups'] ?? [];
        if (empty($groups)) {
            return "{$actionText} (no conditions defined)";
        }

        $groupSummaries = [];
        foreach ($groups as $g) {
            $logic = strtoupper($g['logic'] ?? 'AND');
            $condStrs = [];
            foreach ($g['conditions'] ?? [] as $c) {
                $field = $c['field'] ?? 'field';
                $op = str_replace('_', ' ', $c['operator'] ?? 'equals');
                $val = isset($c['value']) && $c['value'] !== '' ? "\"{$c['value']}\"" : '';
                $condStrs[] = trim("{$field} {$op} {$val}");
            }
            if (!empty($condStrs)) {
                $groupSummaries[] = count($condStrs) > 1 ? '(' . implode(" {$logic} ", $condStrs) . ')' : $condStrs[0];
            }
        }

        $groupLogic = strtoupper($this->group_logic ?: ($this->conditions['group_logic'] ?? 'OR'));
        $body = implode(" {$groupLogic} ", $groupSummaries);

        return "{$actionText} if {$body}";
    }
}
