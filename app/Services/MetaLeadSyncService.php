<?php

namespace App\Services;

use App\Models\PortalAccount;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\LeadMetadata;
use App\Models\LeadFormMapping;
use App\Events\LeadCreated;
use App\Support\LeadMappers\MetaLeadMapper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MetaLeadSyncService
{
    /**
     * Parse budget into a valid decimal amount.
     */
    public static function parseBudget(mixed $rawBudget): float
    {
        if (is_numeric($rawBudget)) {
            return (float) $rawBudget;
        }

        if (is_string($rawBudget)) {
            $lower = strtolower($rawBudget);
            preg_match('/[0-9]+(\.[0-9]+)?/', str_replace(',', '', $rawBudget), $matches);
            if (!empty($matches[0])) {
                $num = (float) $matches[0];
                if (str_contains($lower, 'cr')) {
                    return $num * 10000000;
                }
                if (str_contains($lower, 'l') || str_contains($lower, 'lac') || str_contains($lower, 'lakh')) {
                    return $num * 100000;
                }
                if (str_contains($lower, 'k')) {
                    return $num * 1000;
                }
                if ($num >= 100000) {
                    return $num;
                }
                if ($num < 1000) {
                    return $num * 100000;
                }
                return $num;
            }
        }

        return 7500000.00;
    }

    /**
     * Synchronize leads from Meta (Facebook) Lead Ads into the CRM.
     *
     * @param PortalAccount $account
     * @param string|null $formId Optional specific form ID. If null, all page forms are synced.
     * @param int $limit Maximum leads to fetch per form (default 100).
     * @param int|null $overrideProjectId
     * @param int|null $overrideCampaignId
     * @param string|null $customAccessToken Optional direct token override
     * @return array{
     *     success: bool,
     *     total_forms: int,
     *     total_fetched: int,
     *     total_created: int,
     *     total_duplicates: int,
     *     total_errors: int,
     *     message: string,
     *     forms: array
     * }
     */
    public function sync(
        PortalAccount $account,
        ?string $formId = null,
        int $limit = 100,
        ?int $overrideProjectId = null,
        ?int $overrideCampaignId = null,
        ?string $customAccessToken = null
    ): array {
        $pageId = $account->getCredential('page_id');
        $accessToken = $customAccessToken ?: $account->getCredential('access_token');

        if (empty($accessToken)) {
            return [
                'success' => false,
                'total_forms' => 0,
                'total_fetched' => 0,
                'total_created' => 0,
                'total_duplicates' => 0,
                'total_errors' => 1,
                'message' => 'Missing Page Access Token. Please configure credentials in Settings > Integrations or enter a valid Page Access Token.',
                'forms' => [],
            ];
        }

        // 1. Discover Target Leadgen Form IDs
        $targetForms = []; // array of ['id' => ..., 'name' => ...]

        if (!empty($formId)) {
            $targetForms[] = ['id' => $formId, 'name' => "Form #{$formId}"];
        } else {
            // First try listing from Meta Graph API if pageId is present
            if (!empty($pageId)) {
                try {
                    $formsResponse = Http::timeout(15)->get("https://graph.facebook.com/v19.0/{$pageId}/leadgen_forms", [
                        'access_token' => $accessToken,
                        'fields' => 'id,name,status,leads_count',
                        'limit' => 50,
                    ]);

                    if ($formsResponse->successful() && is_array($formsResponse->json('data'))) {
                        foreach ($formsResponse->json('data') as $f) {
                            $targetForms[] = [
                                'id' => (string) $f['id'],
                                'name' => $f['name'] ?? "Form #{$f['id']}",
                            ];
                        }
                    } else {
                        $errorMsg = $formsResponse->json('error.message') ?? 'Failed to list lead forms from Meta API';
                        Log::warning("MetaLeadSyncService: Could not list forms for page {$pageId}: {$errorMsg}");
                    }
                } catch (Throwable $e) {
                    Log::error("MetaLeadSyncService Exception listing forms: " . $e->getMessage());
                }
            }

            // Also merge any mapped forms in LeadFormMapping if not already added
            if ($account->formMappings()->exists()) {
                $existingIds = array_column($targetForms, 'id');
                foreach ($account->formMappings as $mapping) {
                    if (!in_array($mapping->form_id, $existingIds)) {
                        $targetForms[] = [
                            'id' => $mapping->form_id,
                            'name' => $mapping->form_name ?: "Form #{$mapping->form_id}",
                        ];
                    }
                }
            }
        }

        if (empty($targetForms)) {
            return [
                'success' => false,
                'total_forms' => 0,
                'total_fetched' => 0,
                'total_created' => 0,
                'total_duplicates' => 0,
                'total_errors' => 1,
                'message' => 'No active Meta Leadgen Forms found for Page ID ' . ($pageId ?: 'N/A') . '. Ensure forms are published or enter your Leadgen Form ID directly in the optional field.',
                'forms' => [],
            ];
        }

        $totalFetched = 0;
        $totalCreated = 0;
        $totalDuplicates = 0;
        $totalErrors = 0;
        $formSummaries = [];

        $metaMapper = new MetaLeadMapper();
        $client = Client::first();
        $clientId = $client?->id ?: 1;
        $leadSource = LeadSource::firstOrCreate(['name' => 'meta']);

        foreach ($targetForms as $formInfo) {
            $currentFormId = $formInfo['id'];
            $formName = $formInfo['name'];
            $formFetched = 0;
            $formCreated = 0;
            $formDuplicates = 0;

            try {
                $leadsResponse = Http::timeout(20)->get("https://graph.facebook.com/v19.0/{$currentFormId}/leads", [
                    'access_token' => $accessToken,
                    'limit' => min($limit, 100),
                    'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,is_organic',
                ]);

                if (!$leadsResponse->successful()) {
                    $totalErrors++;
                    $errorText = $leadsResponse->json('error.message') ?? 'HTTP ' . $leadsResponse->status();
                    Log::warning("MetaLeadSyncService: Failed fetching leads for form {$currentFormId}: {$errorText}");
                    $formSummaries[] = [
                        'form_id' => $currentFormId,
                        'form_name' => $formName,
                        'status' => 'error',
                        'error' => $errorText,
                    ];
                    continue;
                }

                $leadsData = $leadsResponse->json('data') ?? [];
                $formFetched = count($leadsData);
                $totalFetched += $formFetched;

                // Match form mapping for project & campaign assignment
                $mapping = LeadFormMapping::where('portal_account_id', $account->id)
                    ->where('form_id', $currentFormId)
                    ->first();

                $projectId = $overrideProjectId ?: ($mapping?->project_id ?: (Project::where('client_id', $clientId)->first()?->id ?: 1));

                foreach ($leadsData as $rawLead) {
                    try {
                        $rawLead['form_id'] = $currentFormId;
                        $rawLead['form_name'] = $formName;
                        $mapped = $metaMapper->map($rawLead);

                        $mobile = $mapped['mobile'] ?: '9876543210';
                        $email = $mapped['email'] ?: 'meta_user@example.com';

                        // 90-Day Deduplication Check
                        $existingLead = Lead::where('client_id', $clientId)
                            ->where('created_at', '>=', now()->subDays(90))
                            ->where(function ($q) use ($mobile, $email) {
                                $q->where('mobile', $mobile);
                                if ($email && $email !== 'meta_user@example.com') {
                                    $q->orWhere('email', $email);
                                }
                            })
                            ->first();

                        if ($existingLead) {
                            $formDuplicates++;
                            $totalDuplicates++;

                            // Record audit trace for duplicate ingestion attempt
                            LeadMetadata::firstOrCreate([
                                'lead_id' => $existingLead->id,
                                'key' => 'meta_duplicate_sync_' . ($rawLead['id'] ?? time()),
                            ], [
                                'value' => json_encode([
                                    'leadgen_id' => $rawLead['id'] ?? null,
                                    'form_id' => $currentFormId,
                                    'campaign_name' => $mapped['campaign'] ?? null,
                                    'synced_at' => now()->toIso8601String(),
                                ]),
                            ]);
                            continue;
                        }

                        // Auto-create or map Campaign from Meta campaign_name
                        $targetCampaignId = $overrideCampaignId;
                        if (!$targetCampaignId && !empty($mapped['campaign'])) {
                            $campaign = Campaign::firstOrCreate(
                                [
                                    'project_id' => $projectId,
                                    'name' => $mapped['campaign'],
                                ],
                                [
                                    'lead_source_id' => $leadSource->id,
                                    'utm_campaign' => Str::slug($mapped['campaign']),
                                    'status' => 'active',
                                ]
                            );
                            $targetCampaignId = $campaign->id;
                        }
                        if (!$targetCampaignId) {
                            $targetCampaignId = $mapping?->campaign_id ?: null;
                        }

                        $numericBudget = self::parseBudget($mapped['budget']);

                        // Create Lead Master Record
                        $lead = Lead::create([
                            'lead_code' => Lead::generateUniqueLeadCode(),
                            'client_id' => $clientId,
                            'project_id' => $projectId,
                            'campaign_id' => $targetCampaignId,
                            'lead_source_id' => $leadSource->id,
                            'name' => $mapped['name'] ?? 'Meta Lead',
                            'mobile' => $mobile,
                            'email' => $email,
                            'city' => $mapped['city'] ?? 'Mumbai',
                            'location' => $mapped['location'] ?? null,
                            'budget' => $numericBudget,
                            'property_type' => $mapped['property_type'] ?? '2 BHK',
                            'requirement' => $mapped['requirement'] ?? "Meta Ads sync from form '{$formName}' (#{$currentFormId})",
                            'status' => 'new',
                            'current_stage' => 'new',
                            'assigned_to' => null,
                        ]);

                        // Save Comprehensive Meta Metadata
                        if (!empty($rawLead['id'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_leadgen_id', 'value' => (string) $rawLead['id']]);
                        }
                        if (!empty($currentFormId)) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_form_id', 'value' => (string) $currentFormId]);
                        }
                        if (!empty($formName)) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_form_name', 'value' => $formName]);
                        }
                        if (!empty($mapped['campaign'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_campaign_name', 'value' => $mapped['campaign']]);
                        }
                        if (!empty($mapped['campaign_id'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_campaign_id', 'value' => (string) $mapped['campaign_id']]);
                        }
                        if (!empty($mapped['ad_set'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_adset_name', 'value' => $mapped['ad_set']]);
                        }
                        if (!empty($mapped['ad_set_id'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_adset_id', 'value' => (string) $mapped['ad_set_id']]);
                        }
                        if (!empty($mapped['ad'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_ad_name', 'value' => $mapped['ad']]);
                        }
                        if (!empty($mapped['ad_id'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_ad_id', 'value' => (string) $mapped['ad_id']]);
                        }
                        if (!empty($rawLead['created_time'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_created_time', 'value' => (string) $rawLead['created_time']]);
                        }
                        if (!empty($mapped['custom_fields'])) {
                            LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_form_answers', 'value' => json_encode($mapped['custom_fields'])]);
                        }

                        LeadMetadata::create([
                            'lead_id' => $lead->id,
                            'key' => 'raw_json',
                            'value' => json_encode($rawLead),
                        ]);

                        // Fire LeadCreated event to reserve credits & auto-assign
                        event(new LeadCreated($lead));

                        $formCreated++;
                        $totalCreated++;
                    } catch (Throwable $e) {
                        $totalErrors++;
                        Log::error("MetaLeadSyncService error creating lead for form {$currentFormId}: " . $e->getMessage());
                    }
                }

                $formSummaries[] = [
                    'form_id' => $currentFormId,
                    'form_name' => $formName,
                    'status' => 'success',
                    'fetched' => $formFetched,
                    'created' => $formCreated,
                    'duplicates' => $formDuplicates,
                ];
            } catch (Throwable $e) {
                $totalErrors++;
                Log::error("MetaLeadSyncService exception for form {$currentFormId}: " . $e->getMessage());
            }
        }

        $message = "Sync completed: {$totalCreated} new lead(s) ingested from " . count($targetForms) . " form(s). ({$totalDuplicates} duplicate(s) skipped).";

        return [
            'success' => $totalErrors === 0 || $totalCreated > 0,
            'total_forms' => count($targetForms),
            'total_fetched' => $totalFetched,
            'total_created' => $totalCreated,
            'total_duplicates' => $totalDuplicates,
            'total_errors' => $totalErrors,
            'message' => $message,
            'forms' => $formSummaries,
        ];
    }
}
