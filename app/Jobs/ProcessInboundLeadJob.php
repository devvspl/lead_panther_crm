<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\WebhookLog;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\LeadMetadata;
use App\Events\LeadCreated;
use App\Support\LeadMappers\MetaLeadMapper;
use App\Support\LeadMappers\GoogleLeadMapper;
use App\Support\LeadMappers\PortalLeadMapper;
use App\Support\LeadMappers\OwnedPortalLeadMapper;
use Throwable;

class ProcessInboundLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public WebhookLog $webhookLog;
    public int $tries = 8;
    public int $timeout = 60;

    public function backoff(): array { return [60, 300, 900, 1800]; }

    public function __construct(WebhookLog $webhookLog)
    {
        $this->webhookLog = $webhookLog;
    }

    public function handle(): void
    {
        $metaLeadLock = null;
        try {
            $account = $this->webhookLog->portalAccount;
            $type = strtolower($account?->type ?: 'meta');
            $raw = is_array($this->webhookLog->payload)
                ? $this->webhookLog->payload
                : (json_decode($this->webhookLog->payload, true) ?: []);

            // If Meta webhook with leadgen_id, fetch full lead field data from Meta Graph API
            if ($type === 'meta') {
                $pageId = $raw['entry'][0]['id'] ?? null;
                if ($pageId && $account?->getCredential('page_id') && $account->getCredential('page_id') !== (string) $pageId) {
                    // A shared app webhook can receive events for any linked Page.
                    $linkedIds = \App\Models\MetaAdAccount::where('portal_account_id', $account->id)->pluck('id');
                    $page = \App\Models\PortalAccount::whereHas('credentials', fn ($q) => $q->where('key_name', 'page_id'))
                        ->whereIn('id', \Illuminate\Support\Facades\DB::table('meta_ad_account_pages')->whereIn('meta_ad_account_id', $linkedIds)->select('portal_account_id'))
                        ->get()->first(fn ($p) => $p->getCredential('page_id') === (string) $pageId);
                    if (!$page) throw new \RuntimeException('Webhook Page is not associated with this integration.');
                    $account = $page;
                }
                $leadgenId = $raw['entry'][0]['changes'][0]['value']['leadgen_id'] ?? $raw['leadgen_id'] ?? null;
                $value = $raw['entry'][0]['changes'][0]['value'] ?? $raw;
                $hasFieldData = !empty($raw['field_data']) || !empty($value['field_data'])
                    || (!empty($value['full_name']) && (!empty($value['phone_number']) || !empty($value['email'])));

                if ($leadgenId && !$hasFieldData && $account) {
                    $token = $account->getCredential('access_token');
                    if (!$token) throw new \RuntimeException('Page access token is required to retrieve this lead.');
                    if ($token) {
                        $fbRes = app(\App\Services\MetaAdAccountService::class)->get((string) $leadgenId, $token, [
                            'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,is_organic,platform',
                        ]);
                        if (empty($fbRes['field_data'])) throw new \RuntimeException('Meta lead details are unavailable; retry after restoring lead retrieval access.');
                        $raw = array_merge($raw, $fbRes);

                    }
                }
            }

            // 1. Normalize Payload via Source Mapper
            $mapped = match ($type) {
                'google' => (new GoogleLeadMapper())->map($raw),
                'portal' => (new PortalLeadMapper())->map($raw),
                'owned' => (new OwnedPortalLeadMapper())->map($raw),
                default => (new MetaLeadMapper())->map($raw),
            };

            // Check form mapping for project and campaign routing
            if ($type === 'meta' && !empty($mapped['leadgen_id'])) {
                $metaLeadLock = \Illuminate\Support\Facades\Cache::lock('meta-lead-import-'.$mapped['leadgen_id'], 60);
                $metaLeadLock->block(5);
            }
            if ($type === 'meta' && !empty($mapped['leadgen_id']) && LeadMetadata::where('key', 'meta_leadgen_id')->where('value', (string) $mapped['leadgen_id'])->exists()) {
                $this->webhookLog->update(['processed' => true, 'error_message' => 'Meta lead already imported.']);
                return;
            }
            \Illuminate\Support\Facades\DB::transaction(function () use ($account, $type, $raw, $mapped) {
                $formId = $mapped['form'] ?? null;
                $mapping = null;
                if ($account && $formId) {
                    $mapping = \App\Models\LeadFormMapping::where('portal_account_id', $account->id)
                        ->where('form_id', $formId)
                        ->first();
                }

                // Determine Client & LeadSource (Form mapping with client takes top priority)
                $client = Client::first();
                $defaultClientId = $client?->id ?: (\App\Models\User::first()?->id ?: 1);
                if ($account) {
                    $accDefault = $account->getCredential('default_client_id');
                    if ($accDefault) $defaultClientId = (int) $accDefault;
                }

                $targetClientId = (!empty($mapping?->client_id))
                    ? (int) $mapping->client_id
                    : ($mapping?->campaign?->project?->client_id ?: $defaultClientId);

                $campaignRoute = $type === 'meta' && $account
                    ? app(\App\Services\MetaCampaignLeadService::class)->routing($account, $mapped) : null;
                if ($campaignRoute) {
                    $targetClientId = $campaignRoute instanceof \App\Models\Project
                        ? (int) $campaignRoute->client_id
                        : (int) ($campaignRoute->project?->client_id ?: $campaignRoute->client_id);
                }
                $clientId = $targetClientId;

                // Inherit project context belonging to the target client
                $projectId = null;
                if (!empty($mapping?->project_id)) {
                    $p = Project::withoutGlobalScopes()->find($mapping->project_id);
                    if ($p) {
                        $projectId = $p->id;
                        if (empty($mapping->client_id) && $p->client_id) {
                            $clientId = $p->client_id;
                        }
                    }
                }
                if (!$projectId && !empty($mapping?->campaign?->project_id)) {
                    $p = Project::withoutGlobalScopes()->find($mapping->campaign->project_id);
                    if ($p) {
                        $projectId = $p->id;
                        if (empty($mapping->client_id) && $p->client_id) {
                            $clientId = $p->client_id;
                        }
                    }
                }
                if (!$projectId) {
                    $projectId = Project::withoutGlobalScopes()->where('client_id', $clientId)->value('id') ?: Project::withoutGlobalScopes()->value('id');
                }

                $campaignId = $campaignRoute instanceof \App\Models\Campaign ? $campaignRoute->id : ($mapping?->campaign_id ?? null);
                if ($campaignRoute) {
                    $projectId = (int) ($campaignRoute instanceof \App\Models\Project ? $campaignRoute->id : $campaignRoute->project_id);
                }
                $leadSource = LeadSource::firstOrCreate(['name' => $mapped['source'] ?? $type]);
                $remoteCampaign = $type === 'meta' && $account ? app(\App\Services\MetaAdAccountService::class)->campaignRecord($account, $mapped) : null;

                // Only fallback to legacy scoped campaign if form has NO explicit client mapping
                if (!$campaignId && $type === 'meta' && $account && empty($mapping?->client_id)) {
                    $scoped = app(\App\Services\MetaAdAccountService::class)->campaignForLead($account, $mapped, $projectId, $leadSource->id);
                    if ($scoped) {
                        $campaignId = $scoped->id;
                        $projectId = $scoped->project_id;
                        $clientId = $scoped->project?->client_id ?: $clientId;
                    }
                }

                // Ensure campaign belongs to the resolved project context
                if ($campaignId && $projectId) {
                    $camp = Campaign::withoutGlobalScopes()->find($campaignId);
                    if ($camp && (int) $camp->project_id !== (int) $projectId) {
                        $matchedCamp = Campaign::withoutGlobalScopes()->where('project_id', $projectId)->where('name', $camp->name)->first();
                        $campaignId = $matchedCamp ? $matchedCamp->id : Campaign::create([
                            'project_id' => $projectId,
                            'name' => $camp->name,
                            'lead_source_id' => $leadSource->id,
                            'utm_campaign' => \Illuminate\Support\Str::slug($camp->name),
                            'status' => 'active',
                        ])->id;
                    }
                }

                // Auto-create or map Campaign from Meta campaign name under the client's project
                if (!$campaignId && $projectId) {
                    $campaignName = !empty($mapped['campaign']) ? $mapped['campaign'] : ($mapping?->form_name ?: 'Meta Lead Campaign');
                    $campaign = \App\Models\Campaign::firstOrCreate(
                        [
                            'project_id' => $projectId,
                            'name' => $campaignName,
                        ],
                        [
                            'lead_source_id' => $leadSource->id,
                            'utm_campaign' => \Illuminate\Support\Str::slug($campaignName),
                            'status' => 'active',
                        ]
                    );
                    $campaignId = $campaign->id;
                }

                // 2. Custom Lead Filtering Rules Check (Builder / Developer Campaign Filters)
                $clientUser = \App\Models\User::find($clientId);
                $organizationId = $clientUser?->organization_id;
                if ($organizationId) {
                    $ruleEngine = app(\App\Services\LeadFilterRuleEngine::class);
                    $filterEval = $ruleEngine->evaluateLead($mapped, $organizationId, $campaignId);

                    if (!$filterEval->passed) {
                        $mobile = $mapped['mobile'] ?? '';
                        $leadIdentifier = $mapped['name'] ?? ($mobile ?: ($mapped['leadgen_id'] ?? 'Inbound Lead'));
                        $ruleEngine->recordLog(
                            organizationId: $organizationId,
                            campaignId: $campaignId,
                            ruleId: $filterEval->matchedRule?->id,
                            leadIdentifier: (string) $leadIdentifier,
                            action: 'skipped',
                            reason: $filterEval->reason,
                            matchedConditions: $filterEval->matchedConditions,
                            payload: $mapped
                        );

                        $this->webhookLog->update([
                            'processed' => true,
                            'error_message' => 'Lead skipped by custom filter rule: ' . $filterEval->reason,
                        ]);
                        return;
                    }
                }

                $mobile = $mapped['mobile'] ?? '';
                $email = !empty($mapped['email']) && $mapped['email'] !== 'unknown@example.com' ? $mapped['email'] : null;

                // 3. 90-Day Duplicate Check
                $existingLead = Lead::where('client_id', $clientId)
                    ->where('created_at', '>=', now()->subDays(90))
                    ->where(function ($q) use ($mobile, $email) {
                        $q->where('mobile', $mobile);
                        if ($email && $email !== 'unknown@example.com') {
                            $q->orWhere('email', $email);
                        }
                    })
                    ->first();

                if ($existingLead) {
                    if ($type === 'meta' && !empty($mapped['leadgen_id'])) {
                        LeadMetadata::firstOrCreate(['lead_id' => $existingLead->id, 'key' => 'meta_leadgen_id', 'value' => (string) $mapped['leadgen_id']]);
                    }
                    // Insert duplicate metadata for audit traceability without creating duplicate Lead or consuming credits
                    LeadMetadata::create([
                        'lead_id' => $existingLead->id,
                        'key' => 'duplicate_utm_source',
                        'value' => $mapped['raw_utm']['utm_source'] ?? $type,
                    ]);

                    LeadMetadata::create([
                        'lead_id' => $existingLead->id,
                        'key' => 'duplicate_raw_json',
                        'value' => json_encode($raw),
                    ]);

                    $this->webhookLog->update([
                        'processed' => true,
                        'error_message' => 'Duplicate lead detected (same mobile/email within 90 days). Logged metadata.',
                    ]);
                    return;
                }

                // Parse budget to valid decimal format
                $rawBudget = $mapped['budget'] ?? null;
                $cleanBudget = null;
                if (is_numeric($rawBudget)) {
                    $cleanBudget = (float) $rawBudget;
                } elseif (is_string($rawBudget)) {
                    preg_match('/[0-9]+(\.[0-9]+)?/', str_replace(',', '', $rawBudget), $matches);
                    if (!empty($matches[0])) {
                        $num = (float) $matches[0];
                        if (str_contains(strtolower($rawBudget), 'cr')) {
                            $cleanBudget = $num * 10000000;
                        } elseif (str_contains(strtolower($rawBudget), 'l')) {
                            $cleanBudget = $num * 100000;
                        } else {
                            $cleanBudget = $num;
                        }
                    }
                }
                $budget = $cleanBudget ?: null;

                // 3. Create New Lead Master Record (project_id removed; inherited via campaign)
                $lead = Lead::create([
                    'lead_code' => Lead::generateUniqueLeadCode(),
                    'client_id' => $clientId,
                    'campaign_id' => $campaignId,
                    'lead_source_id' => $leadSource->id,
                    'name' => !empty($mapped['name']) ? $mapped['name'] : ($mobile ? "Lead {$mobile}" : 'Inbound Lead'),
                    'mobile' => $mobile ?: '',
                    'email' => $email,
                    'city' => !empty($mapped['city']) ? $mapped['city'] : null,
                    'location' => !empty($mapped['location']) ? $mapped['location'] : null,
                    'budget' => $budget,
                    'property_type' => !empty($mapped['property_type']) ? $mapped['property_type'] : null,
                    'requirement' => !empty($mapped['requirement']) ? $mapped['requirement'] : null,
                    'status' => 'new',
                    'current_stage' => 'new',
                    'assigned_to' => null,
                ]);

                // Save raw UTM / Attribution metadata
                LeadMetadata::create([
                    'lead_id' => $lead->id,
                    'key' => 'utm_source',
                    'value' => $mapped['raw_utm']['utm_source'] ?? $type,
                ]);

                if (!empty($mapped['raw_utm']['utm_campaign'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'utm_campaign', 'value' => (string) $mapped['raw_utm']['utm_campaign']]);
                }
                if (!empty($mapped['raw_utm']['utm_content'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'utm_content', 'value' => (string) $mapped['raw_utm']['utm_content']]);
                }
                if (!empty($mapped['leadgen_id'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_leadgen_id', 'value' => (string) $mapped['leadgen_id']]);
                }
                if (!empty($mapped['form'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_form_id', 'value' => (string) $mapped['form']]);
                }
                $formName = $mapped['form_name'] ?? ($mapping?->form_name ?? null);
                if (!empty($formName)) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_form_name', 'value' => (string) $formName]);
                }
                $pageId = $mapped['page_id'] ?? ($raw['entry'][0]['id'] ?? ($raw['page_id'] ?? null));
                if (!empty($pageId)) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_page_id', 'value' => (string) $pageId]);
                }
                if (!empty($mapped['campaign'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_campaign_name', 'value' => $mapped['campaign']]);
                }
                if (!empty($mapped['campaign_id'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_campaign_id', 'value' => (string) $mapped['campaign_id']]);
                }
                if ($remoteCampaign) LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_ad_account_id', 'value' => $remoteCampaign->ad_account_id]);
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
                $createdTime = $mapped['created_time'] ?? ($raw['created_time'] ?? null);
                if (!empty($createdTime)) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_created_time', 'value' => (string) $createdTime]);
                }
                $platform = $mapped['platform'] ?? ($raw['platform'] ?? null);
                if (!empty($platform)) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_platform', 'value' => (string) $platform]);
                }
                $isOrganic = $mapped['is_organic'] ?? ($raw['is_organic'] ?? null);
                if ($isOrganic !== null) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_is_organic', 'value' => $isOrganic ? '1' : '0']);
                }
                if (!empty($mapped['custom_fields'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'meta_form_answers', 'value' => json_encode($mapped['custom_fields'])]);
                }
                if (!empty($mapped['extracted_details']['purpose'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'investment_purpose', 'value' => $mapped['extracted_details']['purpose']]);
                }
                if (!empty($mapped['extracted_details']['call_time'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'preferred_call_time', 'value' => $mapped['extracted_details']['call_time']]);
                }
                if (!empty($mapped['extracted_details']['visit_time'])) {
                    LeadMetadata::create(['lead_id' => $lead->id, 'key' => 'preferred_visit_time', 'value' => $mapped['extracted_details']['visit_time']]);
                }

                LeadMetadata::create([
                    'lead_id' => $lead->id,
                    'key' => 'raw_json',
                    'value' => json_encode($raw),
                ]);

                $this->webhookLog->update([
                    'processed' => true,
                    'error_message' => null,
                ]);

                // Dispatch LeadCreated event to trigger Credit Reservation
                event(new LeadCreated($lead));
            });
        } catch (Throwable $e) {
            $this->webhookLog->update([
                'error_message' => 'Processing exception: ' . $e->getMessage(),
            ]);
            throw $e;
        } finally { $metaLeadLock?->release(); }
    }
}
