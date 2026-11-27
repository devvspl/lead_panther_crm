<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Lead;
use App\Models\LeadDeliveryItem;
use App\Models\LeadDeliveryRevocation;
use App\Models\LeadFilterRule;
use App\Models\LeadMetadata;
use App\Models\MetaAdRecord;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\MetaCampaignLeadService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FilterTruegrowthLeadsByCreatedDateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Only leads with created_at date on 25, 26, or 27 (November 2026) are passed/kept on Truegrowth Realty.
     * All leads with other created_at dates currently assigned/delivered to Truegrowth Realty are revoked
     * and returned to Gulmohar Studio 135.
     */
    public function run(): void
    {
        $this->command?->info('====================================================================');
        $this->command?->info('Filter Truegrowth Realty Leads by Created Date (Only 25, 26, 27)');
        $this->command?->info('Revoking all other dates back to Gulmohar Studio 135');
        $this->command?->info('====================================================================');

        // 1. Locate Organizations
        $targetOrg = Organization::where('id', 43)->first();
        if (!$targetOrg) {
            $targetOrg = Organization::where('name', 'like', '%Truegrowth Realty%')->first()
                ?? Organization::where('name', 'like', '%Truegrowth%')->first();
        }
        if (!$targetOrg) {
            throw new \RuntimeException("Target organization 'Truegrowth Realty' (ID: 43) not found.");
        }

        $sourceOrg = Organization::where('name', 'like', '%Gulmohar Studio 135%')->first()
            ?? Organization::where('name', 'like', '%Gulmohar%')->where('name', 'like', '%135%')->first()
            ?? Organization::where('name', 'like', '%Gulmohar%')->first();
        if (!$sourceOrg) {
            throw new \RuntimeException("Source organization 'Gulmohar Studio 135' not found.");
        }

        $this->command?->info("Target Organization : {$targetOrg->name} (ID: {$targetOrg->id})");
        $this->command?->info("Source Organization : {$sourceOrg->name} (ID: {$sourceOrg->id})");

        // 2. Identify Client Owner Users & All Organization User IDs
        $targetUserIds = User::where('organization_id', $targetOrg->id)->pluck('id')->all();
        $sourceUserIds = User::where('organization_id', $sourceOrg->id)->pluck('id')->all();

        $targetClient = $targetOrg->clients()
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder']))
            ->first() ?? $targetOrg->clients()->first() ?? User::where('organization_id', $targetOrg->id)->first();

        $sourceClient = $sourceOrg->clients()
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder']))
            ->first() ?? $sourceOrg->clients()->first() ?? User::where('organization_id', $sourceOrg->id)->first();

        if (!$targetClient) {
            throw new \RuntimeException("No active user found under target organization '{$targetOrg->name}'.");
        }
        if (!$sourceClient) {
            throw new \RuntimeException("No active user found under source organization '{$sourceOrg->name}'.");
        }

        $this->command?->info("Target Client User  : {$targetClient->name} (ID: {$targetClient->id})");
        $this->command?->info("Source Client User  : {$sourceClient->name} (ID: {$sourceClient->id})");

        // 3. Identify Target and Source Projects & Campaigns
        $targetProject = Project::withoutGlobalScopes()
            ->where('client_id', $targetClient->id)
            ->where(function ($q) {
                $q->where('name', 'like', '%Studio%')
                  ->orWhere('name', 'like', '%Buy%')
                  ->orWhere('name', 'General');
            })
            ->first() ?? Project::withoutGlobalScopes()->where('client_id', $targetClient->id)->first();

        $sourceProject = Project::withoutGlobalScopes()
            ->where('client_id', $sourceClient->id)
            ->where(function ($q) {
                $q->where('name', 'like', '%Studio%')
                  ->orWhere('name', 'like', '%Gulmohar%');
            })
            ->first() ?? Project::withoutGlobalScopes()->where('client_id', $sourceClient->id)->first();

        $targetCampaign = Campaign::withoutGlobalScopes()
            ->where('project_id', $targetProject?->id)
            ->where('name', 'like', '%Studio%')
            ->first() ?? Campaign::withoutGlobalScopes()->where('project_id', $targetProject?->id)->first();

        $sourceCampaign = Campaign::withoutGlobalScopes()
            ->where('project_id', $sourceProject?->id)
            ->where('name', 'like', '%Studio%')
            ->first() ?? Campaign::withoutGlobalScopes()->where('project_id', $sourceProject?->id)->first();

        $this->command?->info("Target Project      : {$targetProject?->name} (ID: {$targetProject?->id})");
        $this->command?->info("Target Campaign     : {$targetCampaign?->name} (ID: {$targetCampaign?->id})");
        $this->command?->info("Source Project      : {$sourceProject?->name} (ID: {$sourceProject?->id})");
        $this->command?->info("Source Campaign     : {$sourceCampaign?->name} (ID: {$sourceCampaign?->id})");

        // 4. STEP A: Revoke any leads currently in Truegrowth Realty whose created_at date is NOT 25, 26, or 27
        $this->command?->info('');
        $this->command?->info('--- STEP A: Checking Truegrowth Realty leads for invalid created dates ---');

        $currentTruegrowthLeads = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($targetUserIds, $targetOrg) {
                $q->whereIn('client_id', $targetUserIds)
                  ->orWhereHas('client', fn($cq) => $cq->where('organization_id', $targetOrg->id));
            })
            ->with(['metadata'])
            ->get();

        $this->command?->info("Found {$currentTruegrowthLeads->count()} leads currently assigned to Truegrowth Realty.");

        $revokedCount = 0;
        $keptCount = 0;

        foreach ($currentTruegrowthLeads as $lead) {
            $isEligibleDate = $this->isEligibleDate($lead);
            $isBuyLead = $this->isEligibleBuyLead($lead);

            // Keep ONLY if date is 25, 26, or 27 AND lead is want_to_buy_studio = buy
            if ($isEligibleDate && $isBuyLead) {
                $keptCount++;
                continue;
            }

            // Otherwise, REVOKE and return to Gulmohar Studio 135
            $reason = !$isEligibleDate 
                ? "Revoked: created_at date ({$this->getLeadFormattedDate($lead)}) is outside permitted dates (25, 26, 27 Nov 2026)."
                : "Revoked: lead does not match Buy condition (want_to_buy_studio = buy).";

            $this->revokeLeadFromTruegrowth($lead, $sourceClient, $sourceCampaign, $targetOrg, $targetClient, $targetUserIds, $reason);
            $revokedCount++;
        }

        $this->command?->info("Step A Completed: {$revokedCount} leads revoked, {$keptCount} eligible leads kept.");

        // 5. STEP B: Pass/transfer any eligible Studio Buy leads created on 25, 26, 27 to Truegrowth Realty
        $this->command?->info('');
        $this->command?->info('--- STEP B: Transferring eligible 25, 26, 27 Buy leads to Truegrowth Realty ---');

        $relevantCampaignIds = Campaign::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('name', 'like', '%Studio%')
                  ->orWhere('name', 'like', '%gulmohar%');
            })
            ->pluck('id');

        $candidateLeads = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($sourceUserIds, $sourceOrg, $relevantCampaignIds) {
                $q->whereIn('client_id', $sourceUserIds)
                  ->orWhereHas('client', fn($cq) => $cq->where('organization_id', $sourceOrg->id))
                  ->orWhereIn('campaign_id', $relevantCampaignIds);
            })
            ->with(['metadata'])
            ->get();

        $passedCount = 0;
        $skippedCount = 0;

        foreach ($candidateLeads as $lead) {
            // Must be created on 25, 26, or 27
            if (!$this->isEligibleDate($lead)) {
                $skippedCount++;
                continue;
            }

            // Must be Buy lead
            if (!$this->isEligibleBuyLead($lead)) {
                $skippedCount++;
                continue;
            }

            // If already on Truegrowth Realty, skip
            if (in_array((int)$lead->client_id, $targetUserIds)) {
                $skippedCount++;
                continue;
            }

            // Transfer to Truegrowth Realty
            DB::transaction(function () use ($lead, $targetClient, $targetCampaign, $sourceOrg) {
                $originalClientId = $lead->client_id;
                $originalCampaignId = $lead->campaign_id;

                $lead->update([
                    'client_id' => $targetClient->id,
                    'campaign_id' => $targetCampaign?->id ?: $lead->campaign_id,
                ]);

                LeadMetadata::updateOrCreate(
                    ['lead_id' => $lead->id, 'key' => 'transferred_from_organization'],
                    ['value' => $sourceOrg->name]
                );
                LeadMetadata::updateOrCreate(
                    ['lead_id' => $lead->id, 'key' => 'transferred_from_client_id'],
                    ['value' => (string) $originalClientId]
                );
                LeadMetadata::updateOrCreate(
                    ['lead_id' => $lead->id, 'key' => 'transferred_from_campaign_id'],
                    ['value' => (string) $originalCampaignId]
                );
                LeadMetadata::updateOrCreate(
                    ['lead_id' => $lead->id, 'key' => 'transferred_at'],
                    ['value' => now()->toIso8601String()]
                );
            });

            $passedCount++;
        }

        $this->command?->info("Step B Completed: {$passedCount} new leads passed to Truegrowth Realty, {$skippedCount} skipped.");

        // 6. Final Count Verification
        $finalTruegrowthLeadsCount = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($targetUserIds, $targetOrg) {
                $q->whereIn('client_id', $targetUserIds)
                  ->orWhereHas('client', fn($cq) => $cq->where('organization_id', $targetOrg->id));
            })
            ->whereNotIn('status', ['inactive', 'deleted'])
            ->count();

        $this->command?->info('');
        $this->command?->info('====================================================================');
        $this->command?->info('SEEDER SUMMARY:');
        $this->command?->info("Target Organization          : {$targetOrg->name}");
        $this->command?->info("Leads Revoked (Other Dates)  : {$revokedCount}");
        $this->command?->info("Leads Kept (Dates 25, 26, 27): {$keptCount}");
        $this->command?->info("Leads Newly Passed           : {$passedCount}");
        $this->command?->info("Final Truegrowth Leads Count : {$finalTruegrowthLeadsCount}");
        $this->command?->info('====================================================================');
    }

    /**
     * Determine if a lead was created on 25, 26, or 27 of November 2026.
     */
    protected function isEligibleDate(Lead $lead): bool
    {
        if (!$lead->created_at) {
            return false;
        }

        $tz = config('app.timezone', 'Asia/Kolkata');
        $local = Carbon::parse($lead->created_at)->timezone($tz);
        $utc = Carbon::parse($lead->created_at)->timezone('UTC');

        $eligibleDates = ['2026-11-25', '2026-11-26', '2026-11-27'];

        return in_array($local->format('Y-m-d'), $eligibleDates)
            || in_array($utc->format('Y-m-d'), $eligibleDates)
            || (in_array((int)$local->day, [25, 26, 27]) && (int)$local->month === 11 && (int)$local->year === 2026);
    }

    /**
     * Get formatted created date for logging.
     */
    protected function getLeadFormattedDate(Lead $lead): string
    {
        if (!$lead->created_at) {
            return 'unknown';
        }
        $tz = config('app.timezone', 'Asia/Kolkata');
        return Carbon::parse($lead->created_at)->timezone($tz)->format('Y-m-d H:i');
    }

    /**
     * Revoke a lead from Truegrowth Realty and return it to Gulmohar Studio 135.
     */
    protected function revokeLeadFromTruegrowth(
        Lead $lead,
        User $sourceClient,
        ?Campaign $sourceCampaign,
        Organization $targetOrg,
        User $targetClient,
        array $targetUserIds,
        string $reason
    ): void {
        DB::transaction(function () use ($lead, $sourceClient, $sourceCampaign, $targetOrg, $targetClient, $targetUserIds, $reason) {
            // 1. Identify original client ID if saved in metadata, otherwise sourceClient
            $originalClientId = (int) ($lead->metadata->firstWhere('key', 'transferred_from_client_id')?->value ?: $sourceClient->id);
            $originalCampaignId = (int) ($lead->metadata->firstWhere('key', 'transferred_from_campaign_id')?->value ?: ($sourceCampaign?->id ?: $lead->campaign_id));

            // If assigned executive belongs to Truegrowth Realty, remove assignment
            $newAssignedTo = in_array((int)$lead->assigned_to, $targetUserIds) ? null : $lead->assigned_to;

            // 2. Revert lead ownership and campaign
            $lead->update([
                'client_id' => $originalClientId,
                'campaign_id' => $originalCampaignId,
                'assigned_to' => $newAssignedTo,
                'do_not_send_to_client' => false,
            ]);

            // 3. Mark any lead delivery items as revoked
            if (Schema::hasTable('lead_delivery_items')) {
                DB::table('lead_delivery_items')
                    ->where('lead_id', $lead->id)
                    ->where(function ($q) use ($targetUserIds, $targetOrg) {
                        $q->whereIn('client_id', $targetUserIds);
                    })
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => now(),
                        'revocation_reason' => $reason,
                        'updated_at' => now(),
                    ]);
            }

            // 4. Record entry in lead_delivery_revocations
            if (Schema::hasTable('lead_delivery_revocations')) {
                DB::table('lead_delivery_revocations')->insert([
                    'lead_id' => $lead->id,
                    'delivery_item_id' => null,
                    'organization_id' => $targetOrg->id,
                    'client_id' => $targetClient->id,
                    'project_id' => $lead->project_id,
                    'campaign_id' => $lead->campaign_id,
                    'original_delivered_at' => $lead->delivered_at ?: $lead->created_at,
                    'revoked_at' => now(),
                    'revoked_by' => 1,
                    'return_reason' => $reason,
                    'previous_status' => 'delivered',
                    'new_status' => 'revoked',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 5. Update metadata tracking
            LeadMetadata::updateOrCreate(
                ['lead_id' => $lead->id, 'key' => 'delivery_revoked_at'],
                ['value' => now()->toIso8601String()]
            );
            LeadMetadata::updateOrCreate(
                ['lead_id' => $lead->id, 'key' => 'delivery_revoke_reason'],
                ['value' => $reason]
            );
            LeadMetadata::updateOrCreate(
                ['lead_id' => $lead->id, 'key' => 'revoked_from_organization'],
                ['value' => $targetOrg->name]
            );

            // Clean transferred flag so it is no longer marked as transferred to Truegrowth
            LeadMetadata::where('lead_id', $lead->id)->where('key', 'transferred_from_organization')->delete();
        });
    }

    /**
     * Check if a lead is an eligible Buy lead matching want_to_buy_studio = buy.
     */
    protected function isEligibleBuyLead(Lead $lead): bool
    {
        // 1. Check meta_form_answers metadata
        $metaAnswers = $lead->metadata->firstWhere('key', 'meta_form_answers')?->value;
        if ($metaAnswers) {
            $answers = is_array($metaAnswers) ? $metaAnswers : (json_decode($metaAnswers, true) ?: []);
            foreach ($answers as $key => $val) {
                $norm = preg_replace('/[^a-z0-9]/', '', strtolower((string)$key));
                if ($norm === 'wanttobuystudio') {
                    return strtolower(trim((string)$val)) === 'buy';
                }
            }
        }

        // 2. Check raw_json field_data metadata
        $rawJson = $lead->metadata->firstWhere('key', 'raw_json')?->value;
        if ($rawJson) {
            $raw = is_array($rawJson) ? $rawJson : (json_decode($rawJson, true) ?: []);
            foreach ($raw['field_data'] ?? [] as $fd) {
                $norm = preg_replace('/[^a-z0-9]/', '', strtolower($fd['name'] ?? ''));
                if ($norm === 'wanttobuystudio') {
                    $vals = $fd['values'] ?? [];
                    $val = is_array($vals) ? ($vals[0] ?? '') : $vals;
                    return strtolower(trim((string)$val)) === 'buy';
                }
            }
        }

        // 3. Check individual lead_metadata keys
        $metaKey = $lead->metadata->first(function ($m) {
            $norm = preg_replace('/[^a-z0-9]/', '', strtolower((string)$m->key));
            return $norm === 'wanttobuystudio';
        });
        if ($metaKey) {
            return strtolower(trim((string)$metaKey->value)) === 'buy';
        }

        return false;
    }
}
