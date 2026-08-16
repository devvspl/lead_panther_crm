<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Pick an assignee User for a lead based on project distribution rules.
     */
    public static function pickAssignee(Lead $lead): ?User
    {
        $rule = static::where('project_id', $lead->project_id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (!$rule) {
            // Fallback: pick any active sales executive
            return User::role('sales-executive')->first() ?? User::first();
        }

        return match ($rule->rule_type) {
            'manual' => null, // Manual queue
            'location' => static::pickLocationWise($rule, $lead),
            'priority' => static::pickPriority($rule, $lead),
            'availability' => static::pickAvailability($rule, $lead),
            default => static::pickRoundRobin($rule, $lead),
        };
    }

    private static function pickRoundRobin(DistributionRule $rule, Lead $lead): ?User
    {
        $execs = User::role('sales-executive')->pluck('id')->toArray();
        if (empty($execs)) {
            $execs = User::pluck('id')->toArray();
        }

        if (empty($execs)) return null;

        // Pick user with least recent assignment
        $lastAssignedUserId = LeadAssignment::latest('id')->value('assigned_to');
        if ($lastAssignedUserId && count($execs) > 1) {
            $key = array_search($lastAssignedUserId, $execs);
            $nextKey = ($key !== false && isset($execs[$key + 1])) ? $key + 1 : 0;
            return User::find($execs[$nextKey]);
        }

        return User::find($execs[0]);
    }

    private static function pickLocationWise(DistributionRule $rule, Lead $lead): ?User
    {
        $config = $rule->config ?? [];
        $locationMap = $config['location_map'] ?? [];
        $leadCity = strtolower($lead->city ?? '');

        foreach ($locationMap as $row) {
            if (isset($row['city']) && strtolower($row['city']) === $leadCity && !empty($row['user_id'])) {
                $user = User::find($row['user_id']);
                if ($user) return $user;
            }
        }

        // Fallback to round robin
        return static::pickRoundRobin($rule, $lead);
    }

    private static function pickPriority(DistributionRule $rule, Lead $lead): ?User
    {
        $config = $rule->config ?? [];
        $priorityUserIds = $config['priority_users'] ?? [];

        foreach ($priorityUserIds as $userId) {
            $user = User::find($userId);
            if ($user && strtolower($user->status ?? 'active') === 'active') {
                return $user;
            }
        }

        return static::pickRoundRobin($rule, $lead);
    }

    private static function pickAvailability(DistributionRule $rule, Lead $lead): ?User
    {
        $onlineExec = User::role('sales-executive')
            ->where('status', 'active')
            ->first();

        return $onlineExec ?? static::pickRoundRobin($rule, $lead);
    }
}
