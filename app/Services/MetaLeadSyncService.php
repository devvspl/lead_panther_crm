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
     * Supports formats like '1 Cr - 1.5 Cr', '1_cr_-_1.5_cr', '₹1.5 Cr',
     * '50 Lakh - 1 Cr', 'under 50 lakh', '2.5 Cr+', etc.
     */
    public static function parseBudget(mixed $rawBudget): float
    {
        if (is_numeric($rawBudget)) {
            return (float) $rawBudget;
        }

        if (is_string($rawBudget)) {
            $normalized = strtolower(str_replace(['_', '₹', '$', ','], [' ', '', '', ''], $rawBudget));
            preg_match('/[0-9]+(\.[0-9]+)?/', $normalized, $matches);
            if (!empty($matches[0])) {
                $num = (float) $matches[0];
                if (str_contains($normalized, 'cr') || str_contains($normalized, 'crore')) {
                    return $num * 10000000;
                }
                if (str_contains($normalized, 'l') || str_contains($normalized, 'lac') || str_contains($normalized, 'lakh')) {
                    return $num * 100000;
                }
                if (str_contains($normalized, 'k')) {
                    return $num * 1000;
                }
                if ($num >= 100000) {
                    return $num;
                }
                if ($num < 1000 && $num > 0) {
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

        Log::info("[MetaLeadSyncService] [Step 1: Init] Sync initiated for PortalAccount #{$account->id} ('{$account->name}'). Page ID: " . ($pageId ?: 'N/A') . ", Form Filter: " . ($formId ?: 'ALL') . ", Lead Limit: {$limit}, Override Project ID: " . ($overrideProjectId ?: 'None') . ", Override Campaign ID: " . ($overrideCampaignId ?: 'None') . ", Custom Token Provided: " . ($customAccessToken ? 'Yes' : 'No'));

        if (empty($accessToken)) {
            Log::warning("[MetaLeadSyncService] [Step 1: Init] Aborted: Missing Page Access Token for Account #{$account->id}.");
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

        // Auto-resolve genuine Page Access Token if User or System-User token was passed
        if (!empty($pageId) && !empty($accessToken)) {
            Log::info("[MetaLeadSyncService] [Step 2: Token Probe] Probing Page endpoint for genuine Page Access Token for Page ID: {$pageId}...");
            try {
                $probe = Http::timeout(10)->get("https://graph.facebook.com/v19.0/{$pageId}", [
                    'fields' => 'id,name,access_token',
                    'access_token' => $accessToken,
                ]);

                if ($probe->successful() && $probe->json('access_token')) {
                    $resolvedPageToken = $probe->json('access_token');
                    $pageName = $probe->json('name') ?? 'Meta Page';
                    if (!empty($resolvedPageToken) && $resolvedPageToken !== $accessToken) {
                        Log::info("[MetaLeadSyncService] [Step 2: Token Probe] Upgraded to dedicated Page Access Token for Page '{$pageName}' (ID: {$pageId}).");
                        $accessToken = $resolvedPageToken;
                        $account->setCredential('access_token', $resolvedPageToken);
                    } else {
                        Log::info("[MetaLeadSyncService] [Step 2: Token Probe] Configured token verified successfully for Page '{$pageName}' (ID: {$pageId}).");
                    }
                } else {
                    $probeError = $probe->json('error.message') ?? "HTTP {$probe->status()}";
                    Log::warning("[MetaLeadSyncService] [Step 2: Token Probe] Probe returned: {$probeError}. Continuing with configured token.");
                }
            } catch (Throwable $e) {
                Log::warning("[MetaLeadSyncService] [Step 2: Token Probe] Token probe exception: {$e->getMessage()}. Continuing with configured token.");
            }
        }

        // 1. Discover Target Leadgen Form IDs
        $targetForms = []; // array of ['id' => ..., 'name' => ...]

        Log::info("[MetaLeadSyncService] [Step 3: Discover Forms] Determining target leadgen forms...");

        if (!empty($formId)) {
            Log::info("[MetaLeadSyncService] [Step 3: Discover Forms] Specific Form ID provided: {$formId}");
            $targetForms[] = ['id' => $formId, 'name' => "Form #{$formId}"];
        } else {
            // First try listing from Meta Graph API if pageId is present
            if (!empty($pageId)) {
                Log::info("[MetaLeadSyncService] [Step 3: Discover Forms] Querying Meta Graph API endpoint https://graph.facebook.com/v19.0/{$pageId}/leadgen_forms...");
                try {
                    $endpoint = "https://graph.facebook.com/v19.0/{$pageId}/leadgen_forms";
                    $afterCursor = null;
                    $seenCursors = [];
                    $maxIterations = 500; // Unlimited in practice (up to 50,000 forms)
                    $iteration = 0;

                    while ($iteration < $maxIterations) {
                        $iteration++;
                        $queryParams = [
                            'access_token' => $accessToken,
                            'fields' => 'id,name,status,leads_count',
                            'limit' => 100,
                        ];
                        if ($afterCursor) {
                            $queryParams['after'] = $afterCursor;
                        }

                        Log::debug("[MetaLeadSyncService] [Step 3: Discover Forms] Requesting forms page #{$iteration}" . ($afterCursor ? " (cursor: {$afterCursor})" : " (initial batch)"));

                        $formsResponse = Http::timeout(20)->get($endpoint, $queryParams);
                        if (!$formsResponse->successful()) {
                            $errorMsg = $formsResponse->json('error.message') ?? 'Failed to list lead forms from Meta API';
                            Log::warning("[MetaLeadSyncService] [Step 3: Discover Forms] Could not list forms for page {$pageId} on iteration {$iteration}: {$errorMsg}");
                            break;
                        }

                        $data = $formsResponse->json('data');
                        if (!is_array($data) || empty($data)) {
                            Log::debug("[MetaLeadSyncService] [Step 3: Discover Forms] Page #{$iteration} returned empty form data.");
                            break;
                        }

                        $batchCount = count($data);
                        $activeCountInBatch = 0;
                        foreach ($data as $f) {
                            $formStatus = strtoupper($f['status'] ?? 'ACTIVE');
                            // Filter out ARCHIVED or INACTIVE forms during full page discovery to prevent 504 timeouts
                            if ($formStatus !== 'ACTIVE') {
                                continue;
                            }
                            $targetForms[] = [
                                'id' => (string) $f['id'],
                                'name' => $f['name'] ?? "Form #{$f['id']}",
                                'status' => $formStatus,
                            ];
                            $activeCountInBatch++;
                        }

                        Log::debug("[MetaLeadSyncService] [Step 3: Discover Forms] Page #{$iteration} added {$activeCountInBatch} active form(s) out of {$batchCount} total. Cumulative active count: " . count($targetForms));

                        if ($batchCount < 100) {
                            Log::debug("[MetaLeadSyncService] [Step 3: Discover Forms] End of API pagination reached ({$batchCount} < 100).");
                            break;
                        }

                        $nextCursor = $formsResponse->json('paging.cursors.after');
                        if (!$nextCursor) {
                            $nextUrl = $formsResponse->json('paging.next');
                            if ($nextUrl) {
                                $parsedUrl = parse_url($nextUrl);
                                if (!empty($parsedUrl['query'])) {
                                    parse_str($parsedUrl['query'], $parsedQuery);
                                    if (!empty($parsedQuery['after'])) {
                                        $nextCursor = $parsedQuery['after'];
                                    }
                                }
                            }
                        }

                        if (!$nextCursor || in_array($nextCursor, $seenCursors, true)) {
                            Log::debug("[MetaLeadSyncService] [Step 3: Discover Forms] No new cursor available or already seen. Halting API form discovery.");
                            break;
                        }

                        $seenCursors[] = $nextCursor;
                        $afterCursor = $nextCursor;
                    }

                    Log::info("[MetaLeadSyncService] [Step 3: Discover Forms] Discovered " . count($targetForms) . " active form(s) from Meta Graph API across {$iteration} request(s).");
                } catch (Throwable $e) {
                    Log::error("[MetaLeadSyncService] [Step 3: Discover Forms] Exception listing forms: " . $e->getMessage());
                }
            }

            // Also merge any mapped active forms in LeadFormMapping if not already added
            if ($account->formMappings()->exists()) {
                $existingIds = array_column($targetForms, 'id');
                $mergedFromDbCount = 0;
                foreach ($account->formMappings as $mapping) {
                    $mStatus = strtolower($mapping->status ?? 'active');
                    if ($mStatus === 'active' && !in_array($mapping->form_id, $existingIds)) {
                        $targetForms[] = [
                            'id' => $mapping->form_id,
                            'name' => $mapping->form_name ?: "Form #{$mapping->form_id}",
                            'status' => 'ACTIVE',
                        ];
                        $mergedFromDbCount++;
                    }
                }
                if ($mergedFromDbCount > 0) {
                    Log::info("[MetaLeadSyncService] [Step 3: Discover Forms] Merged {$mergedFromDbCount} locally mapped active form(s) from database. Total forms to sync: " . count($targetForms));
                }
            }
        }

        if (empty($targetForms)) {
            Log::warning("[MetaLeadSyncService] [Step 3: Discover Forms] No active Meta Leadgen Forms found for Page ID " . ($pageId ?: 'N/A') . ". Aborting sync.");
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

        Log::info("[MetaLeadSyncService] [Step 3: Discover Forms] Total target form(s) to process: " . count($targetForms));

        $totalFetched = 0;
        $totalCreated = 0;
        $totalDuplicates = 0;
        $totalErrors = 0;
        $formSummaries = [];

        $metaMapper = new MetaLeadMapper();
        $client = Client::first();
        if (!$client) {
            $org = \App\Models\Organization::first();
            $client = Client::create([
                'name' => $org?->name ?? 'Default Client Organization',
                'status' => 'active',
            ]);
            Log::info("[MetaLeadSyncService] [Step 4: Defaults] Auto-created fallback Client #{$client->id} ('{$client->name}').");
        }
        $clientId = $client->id;
        $leadSource = LeadSource::firstOrCreate(['name' => 'meta']);
        $defaultConfiguredProjectId = $account->getCredential('default_project_id');

        Log::info("[MetaLeadSyncService] [Step 4: Defaults] System context prepared: Client #{$clientId} ('{$client->name}'), LeadSource 'meta' (ID: {$leadSource->id}), Account Default Project ID: " . ($defaultConfiguredProjectId ?: 'None'));

        $formIndex = 0;
        $totalFormCount = count($targetForms);
        $syncStartTime = microtime(true);
        $maxExecutionSeconds = 40; // Hostinger Nginx has a 60s gateway timeout. 40s leaves a safe 20s margin.
        $timedOutEarly = false;

        foreach ($targetForms as $formInfo) {
            // Execution time guard to prevent 504 Gateway Timeouts on web requests
            if ((microtime(true) - $syncStartTime) > $maxExecutionSeconds) {
                $timedOutEarly = true;
                Log::warning("[MetaLeadSyncService] [Step 5: Safety Guard] Execution time reached {$maxExecutionSeconds}s threshold. Gracefully stopping form processing to avoid 504 Gateway Timeout. Processed {$formIndex}/{$totalFormCount} forms.");
                break;
            }

            $formIndex++;
            $currentFormId = $formInfo['id'];
            $formName = $formInfo['name'];
            $formFetched = 0;
            $formCreated = 0;
            $formDuplicates = 0;

            Log::info("[MetaLeadSyncService] [Step 5: Form {$formIndex}/{$totalFormCount}] Processing Form #{$currentFormId} ('{$formName}')...");

            // Match form mapping for project & campaign assignment
            $mapping = LeadFormMapping::where('portal_account_id', $account->id)
                ->where('form_id', $currentFormId)
                ->first();

            // Resolve valid Project for this form:
            // 1. Override Project -> 2. Form Mapping -> 3. Account Default Project -> 4. Name Match -> 5. Any Existing Project -> 6. Auto-create Project
            $project = null;
            $projectSource = 'none';

            if (!empty($overrideProjectId)) {
                $project = Project::find($overrideProjectId);
                if ($project) {
                    $projectSource = "Override (ID: {$overrideProjectId})";
                }
            }

            if (!$project && !empty($mapping?->project_id)) {
                $project = Project::find($mapping->project_id);
                if ($project) {
                    $projectSource = "LeadFormMapping (ID: {$mapping->project_id})";
                }
            }

            if (!$project && !empty($defaultConfiguredProjectId)) {
                $project = Project::find($defaultConfiguredProjectId);
                if ($project) {
                    $projectSource = "Account Default Project (ID: {$defaultConfiguredProjectId})";
                }
            }

            if (!$project && !empty($formName)) {
                $cleanName = trim(preg_replace('/\(.*?\)/', '', $formName));
                $project = Project::where('name', $formName)
                    ->orWhere('name', 'like', "%{$cleanName}%")
                    ->first();
                if ($project) {
                    $projectSource = "Form Name Match ('{$project->name}')";
                }
            }

            if (!$project) {
                $project = Project::where('client_id', $clientId)->first() ?? Project::first();
                if ($project) {
                    $projectSource = "Client Fallback / First Existing Project";
                }
            }

            // Resolve target client/organization: mapping client -> project client -> default client
            $targetClientId = $mapping?->client_id ?: ($project?->client_id ?: $clientId);

            if (!$project) {
                $project = Project::create([
                    'client_id' => $targetClientId,
                    'name' => $formName ?: 'Meta Lead Ads Project',
                    'status' => 'active',
                ]);
                $projectSource = "Auto-created New Project";
            } elseif ($mapping?->client_id && $project->client_id !== $mapping->client_id) {
                // Keep project client aligned if mapped explicitly
                $project->update(['client_id' => $mapping->client_id]);
                Log::debug("[MetaLeadSyncService] [Step 5: Form #{$currentFormId}] Updated Project #{$project->id} client_id to {$mapping->client_id}");
            }

            $projectId = $project->id;
            $leadClientId = $project->client_id ?: $targetClientId;

            Log::info("[MetaLeadSyncService] [Step 5: Form #{$currentFormId}] Routing resolved: Project #{$projectId} ('{$project->name}') via [{$projectSource}], Target Client ID: {$leadClientId}");

            try {
                $maxLeadsForThisForm = ($limit > 0) ? $limit : 10000;
                $formLeadCursor = null;
                $formLeadsData = [];
                $seenLeadCursors = [];
                $leadBatchIndex = 0;

                Log::info("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] Fetching leads from Meta API (limit: {$maxLeadsForThisForm})...");

                while (count($formLeadsData) < $maxLeadsForThisForm) {
                    $leadBatchIndex++;
                    $batchLimit = min(100, $maxLeadsForThisForm - count($formLeadsData));
                    $leadParams = [
                        'access_token' => $accessToken,
                        'limit' => $batchLimit,
                        'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,is_organic',
                    ];
                    if ($formLeadCursor) {
                        $leadParams['after'] = $formLeadCursor;
                    }

                    Log::debug("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] Requesting leads batch #{$leadBatchIndex} (batch limit: {$batchLimit})" . ($formLeadCursor ? " with cursor {$formLeadCursor}" : " (initial batch)"));

                    $leadsResponse = Http::timeout(20)->get("https://graph.facebook.com/v19.0/{$currentFormId}/leads", $leadParams);

                    if (!$leadsResponse->successful()) {
                        $errorText = $leadsResponse->json('error.message') ?? 'HTTP ' . $leadsResponse->status();
                        if (empty($formLeadsData)) {
                            $totalErrors++;
                            Log::warning("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] Failed fetching leads from Meta API: {$errorText}");
                            $formSummaries[] = [
                                'form_id' => $currentFormId,
                                'form_name' => $formName,
                                'status' => 'error',
                                'error' => $errorText,
                            ];
                        } else {
                            Log::warning("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] Subsequent leads batch #{$leadBatchIndex} error: {$errorText}");
                        }
                        break;
                    }

                    $batchData = $leadsResponse->json('data') ?? [];
                    if (!is_array($batchData) || empty($batchData)) {
                        Log::debug("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] Batch #{$leadBatchIndex} returned empty data.");
                        break;
                    }

                    foreach ($batchData as $rawLead) {
                        $formLeadsData[] = $rawLead;
                    }

                    Log::debug("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] Batch #{$leadBatchIndex} returned " . count($batchData) . " lead(s). Cumulative for this form: " . count($formLeadsData));

                    if (count($batchData) < $batchLimit) {
                        Log::debug("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] Batch #{$leadBatchIndex} had fewer than {$batchLimit} leads. End of leads reached.");
                        break;
                    }

                    $nextLeadCursor = $leadsResponse->json('paging.cursors.after');
                    if (!$nextLeadCursor) {
                        $nextUrl = $leadsResponse->json('paging.next');
                        if ($nextUrl) {
                            $parsedUrl = parse_url($nextUrl);
                            if (!empty($parsedUrl['query'])) {
                                parse_str($parsedUrl['query'], $parsedQuery);
                                if (!empty($parsedQuery['after'])) {
                                    $nextLeadCursor = $parsedQuery['after'];
                                }
                            }
                        }
                    }

                    if (!$nextLeadCursor || in_array($nextLeadCursor, $seenLeadCursors, true)) {
                        Log::debug("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] No further lead cursor. Halting lead fetch.");
                        break;
                    }

                    $seenLeadCursors[] = $nextLeadCursor;
                    $formLeadCursor = $nextLeadCursor;
                }

                $leadsData = $formLeadsData;
                $formFetched = count($leadsData);
                $totalFetched += $formFetched;

                Log::info("[MetaLeadSyncService] [Step 6: Form #{$currentFormId}] Fetched {$formFetched} total lead record(s) from Meta.");

                $leadIndex = 0;
                foreach ($leadsData as $rawLead) {
                    $leadIndex++;
                    try {
                        $rawLead['form_id'] = $currentFormId;
                        $rawLead['form_name'] = $formName;
                        $mapped = $metaMapper->map($rawLead);

                        $mobile = $mapped['mobile'] ?: '';
                        $email = $mapped['email'] ?: '';
                        $metaLeadId = $rawLead['id'] ?? 'unknown';

                        Log::debug("[MetaLeadSyncService] [Step 7: Ingestion] [Form #{$currentFormId}] Lead {$leadIndex}/{$formFetched} (Meta ID: {$metaLeadId}) - Name: '{$mapped['name']}', Phone: {$mobile}, Email: {$email}");

                        // 90-Day Deduplication Check
                        $existingLead = Lead::where('client_id', $leadClientId)
                            ->where('created_at', '>=', now()->subDays(90))
                            ->where(function ($q) use ($mobile, $email) {
                                $q->where('mobile', $mobile);
                                if ($email && $email !== '') {
                                    $q->orWhere('email', $email);
                                }
                            })
                            ->first();

                        if ($existingLead) {
                            $formDuplicates++;
                            $totalDuplicates++;

                            Log::info("[MetaLeadSyncService] [Step 7: Ingestion] [Form #{$currentFormId}] Duplicate skipped for Phone: {$mobile}. Matches existing Lead #{$existingLead->lead_code} (ID: {$existingLead->id}, Created: {$existingLead->created_at->format('Y-m-d H:i')}).");

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
                            Log::debug("[MetaLeadSyncService] [Step 7: Ingestion] Campaign resolved from ad payload: Campaign #{$campaign->id} ('{$campaign->name}')");

                            // Automatically link form mapping to this actual Meta campaign if unassigned
                            if ($mapping && empty($mapping->campaign_id)) {
                                $mapping->update(['campaign_id' => $campaign->id]);
                            }
                        }
                        if (!$targetCampaignId) {
                            $targetCampaignId = $mapping?->campaign_id ?: null;
                            if ($targetCampaignId) {
                                Log::debug("[MetaLeadSyncService] [Step 7: Ingestion] Campaign resolved from Form Mapping: Campaign #{$targetCampaignId}");
                            }
                        }

                        $numericBudget = self::parseBudget($mapped['budget']);

                        // Create Lead Master Record
                        $lead = Lead::create([
                            'lead_code' => Lead::generateUniqueLeadCode(),
                            'client_id' => $leadClientId,
                            'project_id' => $projectId,
                            'campaign_id' => $targetCampaignId,
                            'lead_source_id' => $leadSource->id,
                            'name' => $mapped['name'] ?? 'Meta Lead',
                            'mobile' => $mobile,
                            'email' => $email,
                            'city' => $mapped['city'] ?? '',
                            'location' => $mapped['location'] ?? null,
                            'budget' => $numericBudget,
                            'property_type' => $mapped['property_type'] ?? '',
                            'requirement' => $mapped['requirement'] ?? "Meta Ads sync from form '{$formName}' (#{$currentFormId})",
                            'status' => 'new',
                            'current_stage' => 'new',
                            'assigned_to' => null,
                        ]);

                        Log::info("[MetaLeadSyncService] [Step 7: Ingestion] [Form #{$currentFormId}] Created new Lead: {$lead->name} (#{$lead->lead_code}, ID: {$lead->id}), Mobile: {$lead->mobile}, Project ID: {$lead->project_id}, Campaign ID: " . ($lead->campaign_id ?: 'None'));

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
                        Log::debug("[MetaLeadSyncService] [Step 7: Ingestion] Dispatched LeadCreated event for Lead #{$lead->lead_code}");

                        $formCreated++;
                        $totalCreated++;
                    } catch (Throwable $e) {
                        $totalErrors++;
                        Log::error("[MetaLeadSyncService] [Step 7: Ingestion] Error creating lead for Form {$currentFormId} (Meta ID: " . ($rawLead['id'] ?? 'unknown') . "): " . $e->getMessage());
                    }
                }

                Log::info("[MetaLeadSyncService] [Step 8: Summary] Finished Form #{$currentFormId} ('{$formName}'): {$formCreated} created, {$formDuplicates} duplicates, {$formFetched} fetched from Meta.");

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
                Log::error("[MetaLeadSyncService] [Step 8: Summary] Exception for Form {$currentFormId}: " . $e->getMessage());
            }
        }

        $message = "Sync completed: {$totalCreated} new lead(s) ingested from {$formIndex} form(s). ({$totalDuplicates} duplicate(s) skipped).";
        if ($timedOutEarly) {
            $remainingForms = $totalFormCount - $formIndex;
            $message .= " Note: Processing safely paused after {$formIndex} form(s) ({$remainingForms} remaining) to prevent server gateway timeout. You can select specific forms from the dropdown to sync remaining.";
        }

        Log::info("[MetaLeadSyncService] [Complete] Meta Lead Ads Sync finished for Account #{$account->id} ('{$account->name}'): {$totalCreated} created, {$totalDuplicates} duplicates, {$totalFetched} fetched, {$totalErrors} errors across {$formIndex} of {$totalFormCount} form(s)." . ($timedOutEarly ? " (Paused early by timeout guard)" : ""));

        return [
            'success' => $totalErrors === 0 || $totalCreated > 0,
            'total_forms' => $formIndex,
            'total_fetched' => $totalFetched,
            'total_created' => $totalCreated,
            'total_duplicates' => $totalDuplicates,
            'total_errors' => $totalErrors,
            'message' => $message,
            'forms' => $formSummaries,
        ];
    }
}
