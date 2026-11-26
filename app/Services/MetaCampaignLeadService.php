<?php

namespace App\Services;

use App\Jobs\{FetchMetaCampaignLeads, ProcessInboundLeadJob};
use App\Models\{Campaign, MetaAdAccount, MetaAdRecord, PortalAccount, WebhookLog};
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Validation\ValidationException;

/** Campaign imports never enumerate a Page's unrelated forms. */
class MetaCampaignLeadService
{
    public function saveMapping(MetaAdRecord $record, int $clientId, int $projectId, ?int $campaignId = null): void
    {
        if ($record->kind !== 'campaign') {
            throw ValidationException::withMessages(['mappingProjectId' => 'Only campaigns can be mapped.']);
        }

        $client = \App\Models\User::with('organization')->findOrFail($clientId);
        $project = \App\Models\Project::with('client.organization')->findOrFail($projectId);

        $belongsToClient = (int) $project->client_id === (int) $client->id;
        $belongsToOrg = false;
        if (!$belongsToClient && $client->organization_id && $project->client?->organization_id) {
            $belongsToOrg = (int) $project->client->organization_id === (int) $client->organization_id;
        }

        if (!$belongsToClient && !$belongsToOrg) {
            throw ValidationException::withMessages(['mappingProjectId' => 'The selected project does not belong to the selected organization.']);
        }

        if ($campaignId) {
            $campaign = Campaign::withoutGlobalScopes()->find($campaignId);
            if (!$campaign || (int) $campaign->project_id !== (int) $project->id) {
                $campaignId = null;
            }
        }

        DB::transaction(function () use ($record, $clientId, $projectId, $campaignId) {
            $locked = MetaAdRecord::lockForUpdate()->findOrFail($record->id);
            if (data_get($locked->lead_fetch_state, 'status') === 'running') {
                throw ValidationException::withMessages(['mappingProjectId' => 'Wait for the current lead fetch to finish before changing its mapping.']);
            }
            $locked->update([
                'client_id' => $clientId,
                'project_id' => $projectId,
                'crm_campaign_id' => $campaignId,
            ]);
        });
        app(MetaAdAccountService::class)->audit($record->account, 'campaign_mapped', [
            'remote_campaign_id' => $record->remote_id,
            'client_id' => $clientId,
            'project_id' => $projectId,
            'crm_campaign_id' => $campaignId,
        ]);
    }

    public function start(MetaAdRecord $record): void
    {
        $this->validateMapping($record);
        $dispatch = DB::transaction(function () use ($record) {
            $record = MetaAdRecord::lockForUpdate()->findOrFail($record->id);
            $state = $record->lead_fetch_state;
            if (($state['status'] ?? null) === 'running') return false;
            $mappingKey = ($record->client_id ?? 0) . ':' . ($record->project_id ?? 0) . ':' . ($record->crm_campaign_id ?? 0);
            // Resume a failed cursor only while its destination is unchanged.
            if (($state['status'] ?? null) !== 'failed' || ($state['mapping_key'] ?? null) !== $mappingKey) {
                $state = ['phase' => 'adsets', 'after' => null, 'adsets' => [], 'set_index' => 0, 'forms' => [], 'form_index' => 0,
                    'fetched' => 0, 'created' => 0, 'duplicates' => 0, 'skipped' => 0, 'mapping_key' => $mappingKey,
                    'client_id' => $record->client_id, 'project_id' => $record->project_id, 'crm_campaign_id' => $record->crm_campaign_id];
            }
            $state['run_id'] ??= (string) \Illuminate\Support\Str::uuid();
            $record->update(['lead_fetch_state' => array_replace($state, ['status' => 'running', 'error' => null, 'requested_at' => now()->toIso8601String()])]);
            return true;
        });
        if ($dispatch) {
            app(MetaAdAccountService::class)->audit($record->account, 'campaign_leads_requested', ['remote_campaign_id' => $record->remote_id]);
            FetchMetaCampaignLeads::dispatch($record->id, $record->fresh()->lead_fetch_state['run_id']);
        }
    }

    public function validateMapping(MetaAdRecord $record): mixed
    {
        $project = $record->project ?: ($record->crm_campaign_id ? $record->crmCampaign?->project : null);
        $client = $record->client ?: $project?->client;
        if ($record->kind !== 'campaign' || !$project || !$client || $client->status !== 'active') {
            throw new \RuntimeException('Map this campaign to an active client and CRM project first.');
        }
        if ($record->crm_campaign_id && $record->crmCampaign) {
            return $record->crmCampaign;
        }
        return $project;
    }

    /** One bounded API page per queue job. Returns whether a continuation is needed. */
    public function tick(MetaAdRecord $record): bool
    {
        $lock = Cache::lock('meta-campaign-fetch-'.$record->id, 75);
        if (!$lock->get()) return true;
        $state = $record->lead_fetch_state ?? [];
        $deadline = microtime(true) + 25;
        try {
            if (!in_array($state['status'] ?? '', ['running', 'failed'])) return false;
            $this->validateMapping($record);
            $mappingKey = ($record->client_id ?? 0) . ':' . ($record->project_id ?? 0) . ':' . ($record->crm_campaign_id ?? 0);
            if (!empty($state['mapping_key']) && $state['mapping_key'] !== $mappingKey) {
                throw new \RuntimeException('Campaign mapping changed. Start a new fetch.');
            }
            $account = $record->account;
            if (!$account->access_token || in_array($account->status, ['disconnected', 'expired']) || $account->token_expires_at?->isPast()) {
                throw new \RuntimeException('Reconnect the Ad Account before fetching leads.');
            }
            $state['status'] = 'running';
            $state['error'] = null;
            $api = app(MetaAdAccountService::class);
            $params = ['limit' => 25] + (!empty($state['after']) ? ['after' => $state['after']] : []);

            if ($state['phase'] === 'adsets') {
                $result = $api->get($record->remote_id.'/adsets', $account->access_token, $params + ['fields' => 'id,account_id,name,status,campaign_id']);
                foreach ($result['data'] ?? [] as $row) {
                    $this->assertAccount($account, $row);
                    if ((string) ($row['campaign_id'] ?? '') !== $record->remote_id) throw new \RuntimeException('Ad Set does not belong to this campaign.');
                    $this->store($record, 'adset', $row);
                    if (!in_array((string) $row['id'], $state['adsets'], true)) $state['adsets'][] = (string) $row['id'];
                }
                $state['after'] = $this->cursor($api, $result, $state['after']);
                if (!$state['after']) $state['phase'] = 'ads';
            } elseif ($state['phase'] === 'ads') {
                $setId = $state['adsets'][$state['set_index']] ?? null;
                if (!$setId) $state['phase'] = 'leads';
                else {
                    $result = $api->get($setId.'/ads', $account->access_token, $params + ['fields' => 'id,account_id,name,status,campaign_id,adset_id,creative{id,object_story_spec,asset_feed_spec}']);
                    foreach ($result['data'] ?? [] as $row) {
                        if (in_array((string) $row['id'], $state['batch_ads_processed'] ?? [], true)) continue;
                        $this->assertAccount($account, $row);
                        if ((string) ($row['campaign_id'] ?? '') !== $record->remote_id || (string) ($row['adset_id'] ?? '') !== $setId) throw new \RuntimeException('Ad hierarchy does not match the requested campaign.');
                        $creative = $row['creative'] ?? [];
                        if (!empty($creative['id']) && !isset($creative['object_story_spec']) && !isset($creative['asset_feed_spec'])) {
                            $creative = $api->get((string) $creative['id'], $account->access_token, ['fields' => 'id,object_story_spec,asset_feed_spec']);
                            $row['creative'] = $creative;
                        }
                        $forms = $this->formIds($creative);
                        $row['campaign_form_ids'] = $forms;
                        $this->store($record, 'ad', $row);
                        foreach ($forms as $formId) {
                            $pageId = (string) data_get($creative, 'object_story_spec.page_id', '');
                            if (!$pageId) {
                                $form = $api->get($formId, $account->access_token, ['fields' => 'id,page']);
                                $pageId = (string) data_get($form, 'page.id', '');
                            }
                            $page = $this->page($account, $pageId);
                            $key = $page->id.':'.$formId;
                            $state['forms'][$key] ??= ['id' => $formId, 'page_id' => $pageId, 'portal_id' => $page->id, 'ad_ids' => []];
                            if (!in_array((string) $row['id'], $state['forms'][$key]['ad_ids'], true)) $state['forms'][$key]['ad_ids'][] = (string) $row['id'];
                        }
                        $state['batch_ads_processed'][] = (string) $row['id'];
                        if (microtime(true) >= $deadline) { $record->update(['lead_fetch_state' => $state]); return true; }
                    }
                    $state['after'] = $this->cursor($api, $result, $state['after']);
                    unset($state['batch_ads_processed']);
                    if (!$state['after']) $state['set_index']++;
                }
            } elseif ($state['phase'] === 'leads') {
                $form = array_values($state['forms'])[$state['form_index']] ?? null;
                if (!$form) {
                    $state['status'] = 'completed';
                    $state['completed_at'] = now()->toIso8601String();
                } else {
                    $page = $this->page($account, $form['page_id']);
                    if ($page->id !== $form['portal_id']) throw new \RuntimeException('Associated Page changed. Start a new fetch.');
                    $result = $api->get($form['id'].'/leads', $page->getCredential('access_token'), $params + ['fields' => 'id,created_time,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,is_organic']);
                    foreach ($result['data'] ?? [] as $raw) {
                        if (!empty($raw['id']) && in_array((string) $raw['id'], $state['batch_processed'] ?? [], true)) continue;
                        // A form can be reused by unrelated campaigns. Never infer attribution from form alone.
                        if (!$this->belongsToCampaign($record, $form, $raw)) { $state['skipped']++; continue; }
                        $raw['form_id'] = $form['id'];
                        $raw['campaign_id'] = $record->remote_id;
                        if (in_array((string) $raw['id'], $state['batch_processed'] ?? [], true)) continue;
                        $log = WebhookLog::create(['portal_account_id' => $page->id, 'payload' => json_encode($raw), 'received_at' => now(), 'processed' => false]);
                        DB::transaction(fn () => (new ProcessInboundLeadJob($log))->handle());
                        $log->refresh();
                        if (!$log->processed) throw new \RuntimeException('Lead ingestion did not finish.');
                        if ($log->error_message) $state['duplicates']++;
                        else $state['created']++;
                        $state['fetched']++;
                        $state['batch_processed'][] = (string) $raw['id'];
                        $record->update(['lead_fetch_state' => $state]);
                        if (microtime(true) >= $deadline) return true;
                    }
                    $state['after'] = $this->cursor($api, $result, $state['after']);
                    unset($state['batch_processed']);
                    if (!$state['after']) $state['form_index']++;
                }
            }
            $state['updated_at'] = now()->toIso8601String();
            $record->update(['lead_fetch_state' => $state]);
            $api->audit($account, 'campaign_leads_'.($state['status'] === 'completed' ? 'completed' : 'progress'), [
                'remote_campaign_id' => $record->remote_id, 'stage' => $state['phase'], 'forms' => count($state['forms']),
                'fetched' => $state['fetched'], 'created' => $state['created'], 'duplicates' => $state['duplicates'], 'skipped' => $state['skipped'],
            ]);
            return $state['status'] !== 'completed';
        } catch (\Throwable $e) {
            $state['status'] = 'failed';
            $state['error'] = $e instanceof MetaGraphException ? $e->getMessage().' (HTTP '.$e->httpStatus.', Meta code '.$e->metaCode.')' : 'Fetch paused. Verify campaign mapping, Page access and hierarchy; retry to resume.';
            $record->update(['lead_fetch_state' => $state]);
            app(MetaAdAccountService::class)->audit($record->account, 'campaign_leads_failed', ['remote_campaign_id' => $record->remote_id, 'stage' => $state['phase'] ?? 'mapping', 'message' => $state['error']]);
            throw $e;
        } finally { $lock->release(); }
    }

    private function page(MetaAdAccount $account, string $pageId): PortalAccount
    {
        $page = $account->pages->concat([$account->integration])->unique('id')->first(fn ($page) => (string) $page->getCredential('page_id') === $pageId);
        if (!$pageId || !$page || $page->status !== 'active' || !$page->getCredential('access_token')) throw new \RuntimeException('Associated Page must be connected with lead retrieval access.');
        return $page;
    }

    private function assertAccount(MetaAdAccount $account, array $row): void
    {
        if (isset($row['account_id']) && preg_replace('/^act_/', '', (string) $row['account_id']) !== $account->ad_account_id) throw new \RuntimeException('Unexpected Ad Account in campaign hierarchy.');
    }

    private function store(MetaAdRecord $campaign, string $kind, array $row): void
    {
        $existing = $campaign->account->records()->where('kind', $kind)->where('remote_id', (string) $row['id'])->first();
        $campaign->account->records()->updateOrCreate(['kind' => $kind, 'remote_id' => (string) $row['id']], [
            'ad_account_id' => $campaign->ad_account_id, 'name' => $row['name'] ?? null, 'status' => $row['status'] ?? null,
            'campaign_id' => $campaign->remote_id, 'adset_id' => $row['adset_id'] ?? null,
            'creative_id' => data_get($row, 'creative.id'), 'lead_form_id' => $this->formIds($row['creative'] ?? [])[0] ?? null,
            'data' => array_replace_recursive($existing?->data ?? [], $row), 'synced_at' => now(),
        ]);
    }

    public function formIds(array $creative): array
    {
        $ids = [];
        array_walk_recursive($creative, function ($value, $key) use (&$ids) {
            if ($key === 'lead_gen_form_id' && is_scalar($value) && (string) $value !== '') $ids[] = (string) $value;
        });
        return array_values(array_unique($ids));
    }

    private function cursor(MetaAdAccountService $api, array $result, ?string $previous): ?string
    {
        $next = $api->nextCursor($result);
        if ($next && $next === $previous) throw new \RuntimeException('Meta cursor did not advance.');
        return $next;
    }

    private function belongsToCampaign(MetaAdRecord $record, array $form, array $raw): bool
    {
        if (empty($raw['id']) || (!empty($raw['form_id']) && (string) $raw['form_id'] !== $form['id'])) return false;
        if (!empty($raw['campaign_id']) && (string) $raw['campaign_id'] !== $record->remote_id) return false;
        if (!empty($raw['ad_id']) && !in_array((string) $raw['ad_id'], $form['ad_ids'], true)) return false;
        return !empty($raw['campaign_id']) || !empty($raw['ad_id']);
    }

    /** Used by history imports and future webhooks before legacy form/default routing. */
    public function routing(PortalAccount $page, array $mapped): mixed
    {
        $accounts = MetaAdAccount::where(function ($q) use ($page) {
            $q->whereHas('pages', fn ($pages) => $pages->where('portal_accounts.id', $page->id))
                ->orWhere('portal_account_id', $page->id);
        })->pluck('id');
        $query = MetaAdRecord::whereIn('meta_ad_account_id', $accounts)->where('kind', 'campaign')
            ->where(function ($q) {
                $q->whereNotNull('project_id')->orWhereNotNull('crm_campaign_id');
            });
        if (!empty($mapped['campaign_id'])) $query->where('remote_id', (string) $mapped['campaign_id']);
        elseif (!empty($mapped['ad_id'])) {
            $campaignIds = MetaAdRecord::whereIn('meta_ad_account_id', $accounts)->where('kind', 'ad')->where('remote_id', (string) $mapped['ad_id'])->pluck('campaign_id');
            $query->whereIn('remote_id', $campaignIds);
        } else {
            if (!empty($mapped['form']) && MetaAdRecord::whereIn('meta_ad_account_id', $accounts)->where('kind', 'ad')
                ->where('lead_form_id', (string) $mapped['form'])->whereIn('campaign_id', (clone $query)->select('remote_id'))->exists()) {
                throw new \RuntimeException('Campaign attribution is required for this shared form. Lead held for retry.');
            }
            return null;
        }
        $record = $query->first();
        if (!$record) return null;
        $target = $this->validateMapping($record);
        // A campaign-specific mapping takes precedence; shared form mappings stay untouched.
        return $target;
    }
}
