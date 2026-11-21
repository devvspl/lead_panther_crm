<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Project;
use App\Models\SalesTeam;
use App\Models\Organization;
use App\Models\Client;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\DB;

class LeadPricingService
{
    public const DEFAULT_GLOBAL_COST = 1;

    /**
     * Resolve the exact per-lead credit cost following the priority hierarchy:
     * 1. Project-wise override (highest priority)
     * 2. Team-wise override
     * 3. Organization-wise override
     * 4. Global default setting (fallback)
     *
     * @return array{cost: int, level: string, source: string, formatted: string}
     */
    public function resolveCost(
        ?Project $project = null,
        ?SalesTeam $team = null,
        ?Organization $organization = null
    ): array {
        // 1. Project-wise cost
        if ($project && !is_null($project->lead_cost) && (int) $project->lead_cost > 0) {
            $cost = (int) $project->lead_cost;
            return [
                'cost' => $cost,
                'level' => 'project',
                'source' => "Project ({$project->name})",
                'formatted' => number_format($cost) . ' Credits',
            ];
        }

        // 2. Team-wise cost
        if ($team && !is_null($team->lead_cost) && (int) $team->lead_cost > 0) {
            $cost = (int) $team->lead_cost;
            return [
                'cost' => $cost,
                'level' => 'team',
                'source' => "Sales Team ({$team->name})",
                'formatted' => number_format($cost) . ' Credits',
            ];
        }

        // 3. Organization-wise cost
        $targetOrg = $organization;
        if (!$targetOrg && $project && $project->relationLoaded('client') && $project->client) {
            $targetOrg = $project->client->organization;
        } elseif (!$targetOrg && $project && $project->client_id) {
            $client = Client::with('organization')->find($project->client_id);
            $targetOrg = $client?->organization;
        }

        if ($targetOrg && !is_null($targetOrg->lead_cost) && (int) $targetOrg->lead_cost > 0) {
            $cost = (int) $targetOrg->lead_cost;
            return [
                'cost' => $cost,
                'level' => 'organization',
                'source' => "Organization ({$targetOrg->name})",
                'formatted' => number_format($cost) . ' Credits',
            ];
        }

        // 4. Global Default Cost Fallback
        $globalCost = $this->getGlobalDefaultCost();
        return [
            'cost' => $globalCost,
            'level' => 'global',
            'source' => 'Global Default Setting',
            'formatted' => number_format($globalCost) . ' Credits',
        ];
    }

    /**
     * Resolve cost directly for a given Lead model.
     */
    public function resolveForLead(Lead $lead, ?SalesTeam $team = null): array
    {
        $project = $lead->project_id ? Project::withoutGlobalScopes()->with('client.organization')->find($lead->project_id) : null;
        $client = $lead->client ?: ($lead->client_id ? Client::with('organization')->find($lead->client_id) : null);
        $organization = $client?->organization;

        return $this->resolveCost($project, $team, $organization);
    }

    /**
     * Retrieve the global default lead cost from general_settings or return fallback.
     */
    public function getGlobalDefaultCost(): int
    {
        $setting = GeneralSetting::where('key', 'global_default_lead_cost')->first();
        if ($setting && is_numeric($setting->value) && (int) $setting->value > 0) {
            return \App\Support\LeadCredits::quantity($setting->value, 'global_default_lead_cost', 1);
        }

        return self::DEFAULT_GLOBAL_COST;
    }

    /**
     * Set/update the global default lead cost.
     */
    public function setGlobalDefaultCost(mixed $cost, ?int $userId = null): void
    {
        $cost = \App\Support\LeadCredits::quantity($cost, 'globalDefaultLeadCost', 1);
        GeneralSetting::updateOrCreate(
            ['key' => 'global_default_lead_cost'],
            [
                'value' => (string) $cost,
                'user_id' => $userId ?: (auth()->id() ?? 1),
            ]
        );
    }

    /**
     * Apply lead credit charge to client wallet and record transaction.
     */
    public function chargeForLead(Lead $lead, ?Client $client = null, ?SalesTeam $team = null): ?CreditTransaction
    {
        if ($client && (int) $client->id !== (int) $lead->client_id) throw new \DomainException('Credit recipient must match the delivered lead.');
        if (!CreditTransaction::reserveForLead($lead)) return null;
        return CreditTransaction::withoutGlobalScopes()->where('lead_id', $lead->id)
            ->where('transaction_type', 'lead_consumption')->latest('id')->first();
    }
}
