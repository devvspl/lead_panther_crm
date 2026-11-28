<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadFilterRule;
use App\Models\LeadMetadata;
use App\Models\MetaAdRecord;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\LeadFilterRuleEngine;
use App\Services\MetaCampaignLeadService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransferGulmoharStudioBuyLeadsToTruegrowthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Transfer existing eligible Buy leads from Gulmohar Studio 135 to Truegrowth Realty,
     * configure campaign mapping to Truegrowth Realty's CRM project, and configure
     * the Buy Leads Only filtering rule.
     */
    public function run(): void
    {
        $this->command?->info('=====================================================');
        $this->command?->info('Starting Gulmohar Studio 135 -> Truegrowth Realty Lead Transfer & Configuration');
        $this->command?->info('=====================================================');

        // 1. Dynamically locate source and target organizations
        $sourceOrg = Organization::where('name', 'like', '%Gulmohar Studio 135%')->first();
        if (!$sourceOrg) {
            $sourceOrg = Organization::where('name', 'like', '%Gulmohar%')->where('name', 'like', '%135%')->first();
        }
        if (!$sourceOrg) {
            throw new \RuntimeException("Source organization 'Gulmohar Studio 135' not found in database.");
        }

        $targetOrg = Organization::where('name', 'like', '%Truegrowth Realty%')->first();
        if (!$targetOrg) {
            $targetOrg = Organization::where('name', 'like', '%Truegrowth%')->first();
        }
        if (!$targetOrg) {
            throw new \RuntimeException("Target organization 'Truegrowth Realty' not found in database.");
        }

        $this->command?->info("Source Organization: {$sourceOrg->name} (ID: {$sourceOrg->id})");
        $this->command?->info("Target Organization: {$targetOrg->name} (ID: {$targetOrg->id})");

        // 2. Identify source and target client owner users
        $sourceClient = $sourceOrg->clients()
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder']))
            ->first() ?? $sourceOrg->clients()->first();

        $targetClient = $targetOrg->clients()
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder']))
            ->first() ?? $targetOrg->clients()->first();

        if (!$targetClient) {
            throw new \RuntimeException("Target organization '{$targetOrg->name}' does not have any active client users.");
        }

        $this->command?->info("Source Client Owner: {$sourceClient?->name} (ID: {$sourceClient?->id})");
        $this->command?->info("Target Client Owner: {$targetClient->name} (ID: {$targetClient->id})");

        // 3. Find or create the target CRM project for Truegrowth Realty
        $targetProject = Project::withoutGlobalScopes()
            ->where('client_id', $targetClient->id)
            ->where(function ($q) {
                $q->where('name', 'like', '%Studio%')
                  ->orWhere('name', 'like', '%Buy%')
                  ->orWhere('name', 'General');
            })
            ->first();

        if (!$targetProject) {
            $targetProject = Project::withoutGlobalScopes()
                ->where('client_id', $targetClient->id)
                ->first();
        }

        if (!$targetProject) {
            $targetProject = Project::create([
                'client_id' => $targetClient->id,
                'name' => 'Studio Buy Leads',
                'status' => 'active',
            ]);
            $this->command?->info("Created new CRM Project 'Studio Buy Leads' for {$targetOrg->name} (ID: {$targetProject->id})");
        } else {
            $this->command?->info("Found existing CRM Project '{$targetProject->name}' for {$targetOrg->name} (ID: {$targetProject->id})");
        }

        // Validate that target project belongs to Truegrowth Realty
        $projectOwner = User::find($targetProject->client_id);
        if (!$projectOwner || (int)$projectOwner->organization_id !== (int)$targetOrg->id) {
            throw new \RuntimeException("Validation failed: Target project '{$targetProject->name}' does not belong to organization '{$targetOrg->name}'.");
        }

        // 4. Locate Facebook Studio Campaign & Map to Target Project
        $metaRecord = MetaAdRecord::where('kind', 'campaign')
            ->where(function ($q) use ($sourceClient) {
                $q->where('remote_id', '52591282486875')
                  ->orWhere('name', 'like', '%Studio Noida Exp 50%')
                  ->orWhere(function ($sub) use ($sourceClient) {
                      if ($sourceClient) {
                          $sub->where('client_id', $sourceClient->id)->where('name', 'like', '%Studio%');
                      }
                  });
            })
            ->first();

        $targetCampaignName = $metaRecord?->name ?: 'Studio Noida Exp 50 – 2 gulmohar';

        $targetCampaign = Campaign::withoutGlobalScopes()
            ->where('project_id', $targetProject->id)
            ->where('name', $targetCampaignName)
            ->first();

        if (!$targetCampaign) {
            $targetCampaign = Campaign::create([
                'project_id' => $targetProject->id,
                'name' => $targetCampaignName,
                'utm_campaign' => Str::slug($targetCampaignName),
                'status' => 'active',
            ]);
            $this->command?->info("Created CRM Campaign '{$targetCampaign->name}' under {$targetProject->name} (ID: {$targetCampaign->id})");
        } else {
            $this->command?->info("Found CRM Campaign '{$targetCampaign->name}' under {$targetProject->name} (ID: {$targetCampaign->id})");
        }

        if ($metaRecord) {
            app(MetaCampaignLeadService::class)->saveMapping(
                $metaRecord,
                (int) $targetClient->id,
                (int) $targetProject->id,
                $targetCampaign->id
            );
            $this->command?->info("Mapped Facebook Campaign '{$metaRecord->name}' (Remote ID: {$metaRecord->remote_id}) to {$targetOrg->name} Project '{$targetProject->name}'.");
        }

        // 5. Configure "Buy Leads Only" Filtering Rule
        $ruleConditions = [
            'group_logic' => 'OR',
            'groups' => [
                [
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'field' => 'want_to_buy_studio',
                            'operator' => 'equals',
                            'value' => 'buy',
                        ],
                    ],
                ],
            ],
        ];

        // Ensure rule exists under both organizations so evaluation is seamless in all contexts
        foreach ([$sourceOrg->id, $targetOrg->id] as $orgId) {
            LeadFilterRule::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'name' => 'Buy Leads Only',
                ],
                [
                    'action' => 'include',
                    'group_logic' => 'OR',
                    'conditions' => $ruleConditions,
                    'is_active' => true,
                    'priority' => 100,
                    'description' => 'Include only leads where want_to_buy_studio equals buy. Exclude rent and all other values.',
                    'campaign_id' => null,
                    'project_id' => null,
                    'created_by' => 1,
                ]
            );
        }
        $this->command?->info("Configured 'Buy Leads Only' filtering rule (include leads where want_to_buy_studio = buy).");

        // 6. Query and transfer eligible Buy leads
        $relevantCampaignIds = Campaign::withoutGlobalScopes()
            ->where(function ($q) use ($sourceOrg, $targetProject) {
                $q->where('name', 'like', '%Studio%')
                  ->orWhere('name', 'like', '%gulmohar%');
            })
            ->pluck('id')
            ->push($targetCampaign->id);

        if ($metaRecord && $metaRecord->crm_campaign_id) {
            $relevantCampaignIds->push($metaRecord->crm_campaign_id);
        }

        $leadsToInspect = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($sourceClient, $sourceOrg, $relevantCampaignIds, $targetClient) {
                if ($sourceClient) {
                    $q->where('client_id', $sourceClient->id);
                }
                $q->orWhereHas('client', fn($cq) => $cq->where('organization_id', $sourceOrg->id))
                  ->orWhereIn('campaign_id', $relevantCampaignIds)
                  ->orWhere(function ($sub) use ($targetClient) {
                      $sub->where('client_id', $targetClient->id)
                          ->whereHas('metadata', fn($mq) => $mq->where('key', 'transferred_from_organization'));
                  });
            })
            ->with(['metadata'])
            ->get();

        $this->command?->info("Total candidate leads found for inspection: " . $leadsToInspect->count());

        $eligibleCount = 0;
        $transferredCount = 0;
        $revokedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($leadsToInspect as $lead) {
            try {
                $isBuy = $this->isEligibleBuyLead($lead);
                $isDateEligible = $this->isEligibleDate($lead);
                $isCurrentlyOnTarget = (int)$lead->client_id === (int)$targetClient->id;

                // If currently on Truegrowth Realty but NOT eligible date (only 25, 26, 27) or NOT Buy, REVOKE it back to Gulmohar
                if ($isCurrentlyOnTarget && (!$isBuy || !$isDateEligible)) {
                    DB::transaction(function () use ($lead, $sourceClient, $sourceOrg, $targetOrg, $targetCampaign) {
                        $origClientId = (int) ($lead->metadata->firstWhere('key', 'transferred_from_client_id')?->value ?: $sourceClient->id);
                        $origCampaignId = (int) ($lead->metadata->firstWhere('key', 'transferred_from_campaign_id')?->value ?: $lead->campaign_id);

                        $lead->update([
                            'client_id' => $origClientId,
                            'campaign_id' => $origCampaignId,
                            'assigned_to' => null,
                        ]);

                        LeadMetadata::updateOrCreate(
                            ['lead_id' => $lead->id, 'key' => 'delivery_revoked_at'],
                            ['value' => now()->toIso8601String()]
                        );
                        LeadMetadata::updateOrCreate(
                            ['lead_id' => $lead->id, 'key' => 'delivery_revoke_reason'],
                            ['value' => 'Created at date not in [25, 26, 27] Nov 2026. Revoked to Gulmohar Studio 135.']
                        );
                        LeadMetadata::where('lead_id', $lead->id)->where('key', 'transferred_from_organization')->delete();
                    });

                    $revokedCount++;
                    continue;
                }

                // If not eligible (wrong date or not buy), skip
                if (!$isBuy || !$isDateEligible) {
                    $skippedCount++;
                    continue;
                }

                $eligibleCount++;

                // Check if lead has already been transferred to target client and campaign (idempotency)
                if ((int)$lead->client_id === (int)$targetClient->id && (int)$lead->campaign_id === (int)$targetCampaign->id) {
                    $skippedCount++;
                    continue;
                }

                // Transfer lead within transaction
                DB::transaction(function () use ($lead, $targetClient, $targetCampaign, $sourceOrg, $targetOrg) {
                    $originalClientId = $lead->client_id;
                    $originalCampaignId = $lead->campaign_id;

                    $lead->update([
                        'client_id' => $targetClient->id,
                        'campaign_id' => $targetCampaign->id,
                    ]);

                    LeadMetadata::updateOrCreate(
                        ['lead_id' => $lead->id, 'key' => 'transferred_from_organization'],
                        ['value' => $sourceOrg->name]
                    );
                    LeadMetadata::updateOrCreate(
                        ['lead_id' => $lead->id, 'key' => 'transferred_to_organization'],
                        ['value' => $targetOrg->name]
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

                $transferredCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                Log::error("Failed to transfer lead ID {$lead->id}: " . $e->getMessage(), ['exception' => $e]);
                $this->command?->error("Error transferring lead ID {$lead->id}: " . $e->getMessage());
            }
        }

        // 7. Output and Log Summary
        $summary = [
            'source_organization' => $sourceOrg->name,
            'target_organization' => $targetOrg->name,
            'target_project' => "{$targetProject->name} (ID: {$targetProject->id})",
            'target_campaign' => "{$targetCampaign->name} (ID: {$targetCampaign->id})",
            'total_inspected' => $leadsToInspect->count(),
            'eligible_leads' => $eligibleCount,
            'transferred_leads' => $transferredCount,
            'skipped_leads' => $skippedCount,
            'failed_leads' => $failedCount,
        ];

        Log::info('TransferGulmoharStudioBuyLeadsToTruegrowthSeeder completed.', $summary);

        $this->command?->info('');
        $this->command?->info('=====================================================');
        $this->command?->info('TRANSFER SUMMARY:');
        $this->command?->info("Source Organization : {$summary['source_organization']}");
        $this->command?->info("Target Organization : {$summary['target_organization']}");
        $this->command?->info("Target CRM Project  : {$summary['target_project']}");
        $this->command?->info("Target CRM Campaign : {$summary['target_campaign']}");
        $this->command?->info("Total Inspected     : {$summary['total_inspected']}");
        $this->command?->info("Eligible Buy Leads  : {$summary['eligible_leads']}");
        $this->command?->info("Transferred Leads   : {$summary['transferred_leads']}");
        $this->command?->info("Skipped Leads       : {$summary['skipped_leads']}");
        $this->command?->info("Failed Leads        : {$summary['failed_leads']}");
        $this->command?->info('=====================================================');
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

    /**
     * Determine if a lead was created on 25, 26, or 27 of November 2026.
     */
    protected function isEligibleDate(Lead $lead): bool
    {
        // 1. If meta_created_time exists, it takes precedence to ensure October leads are never treated as November leads
        $metaTime = $lead->metadata->firstWhere('key', 'meta_created_time')?->value;
        if ($metaTime) {
            $metaDt = \Carbon\Carbon::parse($metaTime)->timezone('Asia/Kolkata');
            return (int)$metaDt->year === 2026 && (int)$metaDt->month === 11 && in_array((int)$metaDt->day, [25, 26, 27]);
        }

        if (!$lead->created_at) {
            return false;
        }

        $tz = config('app.timezone', 'Asia/Kolkata');
        $local = \Carbon\Carbon::parse($lead->created_at)->timezone($tz);
        $utc = \Carbon\Carbon::parse($lead->created_at)->timezone('UTC');

        $eligibleDates = ['2026-11-25', '2026-11-26', '2026-11-27'];

        return in_array($local->format('Y-m-d'), $eligibleDates)
            || in_array($utc->format('Y-m-d'), $eligibleDates)
            || (in_array((int)$local->day, [25, 26, 27]) && (int)$local->month === 11 && (int)$local->year === 2026);
    }
}
