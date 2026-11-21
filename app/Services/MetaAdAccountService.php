<?php

namespace App\Services;

use App\Models\{AuditLog, MetaAdAccount, MetaAdRecord, PortalAccount};
use Illuminate\Support\Facades\{Cache, DB, Http};

class MetaAdAccountService
{
    public function get(string $path, string $token, array $parameters = []): array
    {
        // Never follow API-supplied URLs (they can contain tokens). Only replay cursors.
        $response = Http::withToken($token)->connectTimeout(5)->timeout(15)
            ->get('https://graph.facebook.com/'.config('services.meta.graph_version', 'v27.0').'/'.$path, $parameters);
        $code = (int) $response->json('error.code');
        if (!$response->successful() || $response->json('error')) {
            throw new MetaGraphException($response->status() === 429 || $response->serverError()
                || in_array($code, [1, 2, 4, 17, 32, 613, 80004, 80005]) || (bool) $response->json('error.is_transient'),
                $code === 190, max(60, min(3600, (int) $response->header('Retry-After') ?: 300)), $response->status(), $code);
        }
        return $response->json() ?: [];
    }

    public function test(MetaAdAccount $account): void
    {
        try {
            if (!$account->access_token) throw new \RuntimeException('Add an Ad Account access token to reconnect.');
            $token = $account->access_token;
            $root = $this->get('act_'.$account->ad_account_id, $token, ['fields' => 'id,account_id,name,account_status,business{id,name}']);
            if ((string) ($root['account_id'] ?? '') !== $account->ad_account_id) throw new \RuntimeException('Meta returned a different Ad Account.');
            $this->get('act_'.$account->ad_account_id.'/campaigns', $token, ['fields' => 'id', 'limit' => 1]);
            $user = $this->get('me', $token, ['fields' => 'id,name']);
            $expiry = $account->token_expires_at;
            $appId = $account->integration->getCredential('app_id');
            $secret = $account->integration->getCredential('app_secret');
            if ($appId && $secret) {
                $debug = $this->get('debug_token', $appId.'|'.$secret, ['input_token' => $token]);
                if (!data_get($debug, 'data.is_valid') || data_get($debug, 'data.type') === 'PAGE') throw new MetaGraphException(false, true);
                $scopes = data_get($debug, 'data.scopes', []);
                if (!array_intersect(['ads_read', 'ads_management'], $scopes)) throw new MetaGraphException(false, false);
                $expires = (int) data_get($debug, 'data.expires_at');
                $expiry = $expires > 0 ? \Carbon\Carbon::createFromTimestamp($expires) : null;
            }
            if ($expiry?->isPast()) throw new MetaGraphException(false, true);
            $account->update(['status' => 'connected', 'business_id' => data_get($root, 'business.id') ?: $account->business_id,
                'business_name' => data_get($root, 'business.name'), 'facebook_user_id' => $user['id'] ?? null,
                'connection_name' => $user['name'] ?? $account->connection_name, 'token_expires_at' => $expiry,
                'last_error' => null, 'retry_after' => null]);
            $this->audit($account, 'tested');
        } catch (\Throwable $e) {
            $this->failure($account, $e);
            throw new \RuntimeException($account->fresh()->last_error);
        }
    }

    public function sync(MetaAdAccount $account): void
    {
        $lock = Cache::lock('meta-ad-account-'.$account->id, 75);
        if (!$lock->get()) return;
        try {
            $account->refresh();
            if (!in_array($account->status, ['connected', 'error', 'rate_limited']) || $account->retry_after?->isFuture()) return;
            if (!$account->access_token || $account->token_expires_at?->isPast()) throw new MetaGraphException(false, true);
            $token = $account->access_token;
            $account->update(['status' => 'connected', 'retry_after' => null]);
            $deadline = microtime(true) + 25;
            $state = $account->sync_state ?? ['edge' => 0, 'after' => null, 'page_errors' => 0];
            $this->audit($account, 'sync_started', ['resuming' => $account->sync_state !== null, 'edge' => $state['edge']]);
            $edges = [
                ['campaign', 'campaigns', 'id,account_id,name,status,objective,created_time,updated_time'],
                ['adset', 'adsets', 'id,account_id,name,status,campaign_id,daily_budget,lifetime_budget,created_time,updated_time'],
                ['ad', 'ads', 'id,account_id,name,status,campaign_id,adset_id,creative{id,object_story_spec},created_time,updated_time'],
                ['insight', 'insights', 'account_id,campaign_id,campaign_name,impressions,clicks,spend,reach,actions,date_start,date_stop'],
            ];
            while ($state['edge'] < count($edges)) {
                if ($account->fresh()->status !== 'connected' || $account->fresh()->access_token !== $token) return;
                [$kind, $edge, $fields] = $edges[$state['edge']];
                $params = ['fields' => $fields];
                if ($state['after']) $params['after'] = $state['after'];
                if ($kind === 'insight') $params += ['level' => 'campaign', 'date_preset' => 'last_30d', 'time_increment' => 1];
                $result = $this->get('act_'.$account->ad_account_id.'/'.$edge, $token, $params + ['limit' => 100]);
                if ($account->fresh()->status !== 'connected' || $account->fresh()->access_token !== $token) return;
                $new = $updated = 0;
                foreach ($result['data'] ?? [] as $row) {
                    if ((string) ($row['account_id'] ?? '') !== $account->ad_account_id) throw new \RuntimeException('Unexpected Ad Account in synchronization response.');
                    $id = $kind === 'insight' ? ($row['campaign_id'].'_'.$row['date_start']) : $row['id'];
                    $record = $account->records()->updateOrCreate(['kind' => $kind, 'remote_id' => $id], [
                        'ad_account_id' => $account->ad_account_id, 'name' => $row['name'] ?? $row['campaign_name'] ?? null,
                        'campaign_id' => $kind === 'campaign' ? $row['id'] : ($row['campaign_id'] ?? null),
                        'adset_id' => $row['adset_id'] ?? null, 'status' => $row['status'] ?? null,
                        'report_date' => $row['date_start'] ?? null, 'data' => $row, 'synced_at' => now(),
                    ]);
                    if ($record->wasRecentlyCreated) $new++;
                    elseif ($record->wasChanged()) $updated++;
                }
                $next = $this->nextCursor($result);
                $this->audit($account, 'records_fetched', ['kind' => $kind, 'fetched' => count($result['data'] ?? []),
                    'new' => $new, 'updated' => $updated, 'has_more' => $next !== null]);
                if ($next && $next === $state['after']) throw new \RuntimeException('Meta cursor did not advance.');
                $state['after'] = $next;
                if (!$next) $state['edge']++;
                $account->update(['sync_state' => $state]);
                if (microtime(true) >= $deadline) return;
            }
            if (!$this->syncPages($account, $state, $deadline)) return;
            if ($account->fresh()->status !== 'connected' || $account->fresh()->access_token !== $token) return;
            $pageErrors = $state['page_errors'];
            $account->update(['last_synced_at' => now(), 'status' => 'connected', 'retry_after' => null,
                'sync_state' => null,
                'last_error' => $pageErrors ? 'Ads synced. Some Pages need separate Page access; existing mappings were preserved.' : null]);
            $this->audit($account, 'synced', ['records' => $account->records()->count(), 'page_access_errors' => $pageErrors,
                'counts' => $account->records()->selectRaw('kind, COUNT(*) as total')->groupBy('kind')->pluck('total', 'kind')->all()]);
        } catch (\Throwable $e) {
            $this->failure($account, $e);
            // Throw only a sanitized error; queue failed-job payloads must never contain a token URL.
            throw new \RuntimeException($account->fresh()->last_error);
        } finally { $lock->release(); }
    }

    public function nextCursor(array $result): ?string
    {
        if (empty($result['paging']['next'])) return null;
        $after = data_get($result, 'paging.cursors.after');
        if (!$after) {
            parse_str(parse_url($result['paging']['next'], PHP_URL_QUERY) ?: '', $query);
            $after = $query['after'] ?? null;
        }
        if (!$after) throw new \RuntimeException('Meta pagination cursor missing.');
        return (string) $after;
    }

    private function syncPages(MetaAdAccount $account, array &$state, float $deadline): bool
    {
        do {
            if ($account->fresh()->status !== 'connected') return false;
            if (!array_key_exists('pages', $state)) {
                try {
                    $params = ['fields' => 'id,name', 'limit' => 100];
                    if ($state['after']) $params['after'] = $state['after'];
                    $result = $this->get('act_'.$account->ad_account_id.'/promote_pages', $account->access_token, $params);
                    $state['pages'] = $result['data'] ?? [];
                    $next = $this->nextCursor($result);
                    if ($next && $next === $state['after']) throw new \RuntimeException('Meta cursor did not advance.');
                    $state['after'] = $next;
                } catch (MetaGraphException $e) {
                    if ($e->retryable || $e->expired) throw $e;
                    $state['page_errors']++;
                    return true;
                }
            }
            while ($state['pages']) {
                if ($account->fresh()->status !== 'connected') return false;
                $page = $state['pages'][0];
                try {
                    Cache::lock('meta-page-discovery-'.$page['id'], 30)->block(2, function () use ($account, $page) {
                    $existing = PortalAccount::where('type', 'meta')->whereHas('credentials', fn ($q) => $q->where('key_name', 'page_id'))
                        ->get()->first(fn ($p) => $p->getCredential('page_id') === (string) $page['id']);
                    if ($existing) {
                        $account->pages()->syncWithoutDetaching([$existing->id]);
                        return;
                    }
                    $info = $this->get((string) $page['id'], $account->access_token, ['fields' => 'id,name,access_token']);
                    // An ads token is never substituted for a Page token.
                    if (!$existing && empty($info['access_token'])) throw new MetaGraphException(false, false);
                    DB::transaction(function () use ($account, $page, $info, $existing) {
                        $portal = $existing;
                        if (!$portal) {
                            $portal = PortalAccount::create(['type' => 'meta', 'name' => $page['name'], 'status' => 'active']);
                            $portal->setCredential('page_id', (string) $page['id']);
                            $portal->setCredential('access_token', $info['access_token']);
                            foreach (['app_id', 'app_secret', 'verify_token', 'default_client_id', 'default_project_id'] as $key) {
                                if ($value = $account->integration->getCredential($key)) $portal->setCredential($key, $value);
                            }
                        }
                        $account->pages()->syncWithoutDetaching([$portal->id]);
                    });
                    });
                } catch (MetaGraphException $e) {
                    if ($e->retryable || $e->expired) throw $e;
                    $state['page_errors']++;
                } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) { throw $e; }
                catch (\Throwable) { $state['page_errors']++; }
                array_shift($state['pages']);
                $account->update(['sync_state' => $state]);
                if (microtime(true) >= $deadline) return false;
            }
            unset($state['pages']);
            if (!$state['after']) return true;
            $account->update(['sync_state' => $state]);
        } while (microtime(true) < $deadline);
        return false;
    }

    private function failure(MetaAdAccount $account, \Throwable $error): void
    {
        $expired = $error instanceof MetaGraphException && $error->expired;
        $retryable = !($error instanceof MetaGraphException) || $error->retryable;
        $message = $error instanceof MetaGraphException ? $error->getMessage() : 'Synchronization could not finish. Test the connection and retry.';
        $account->update(['status' => $expired ? 'expired' : ($retryable ? 'rate_limited' : 'error'), 'last_error' => $message,
            'retry_after' => $retryable ? now()->addSeconds($error instanceof MetaGraphException ? $error->retryAfter : 300) : null]);
        $this->audit($account, 'sync_failed', ['message' => $message,
            'http_status' => $error instanceof MetaGraphException ? $error->httpStatus : null,
            'meta_code' => $error instanceof MetaGraphException ? $error->metaCode : null]);
    }

    public function campaignRecord(PortalAccount $page, array $mapped): ?MetaAdRecord
    {
        if (empty($mapped['campaign_id'])) return null;
        return MetaAdRecord::where('kind', 'campaign')->where('remote_id', (string) $mapped['campaign_id'])
            ->whereHas('account.pages', fn ($q) => $q->where('portal_accounts.id', $page->id))->first();
    }

    public function campaignForLead(PortalAccount $page, array $mapped, ?int $projectId, int $sourceId): ?\App\Models\Campaign
    {
        $record = $this->campaignRecord($page, $mapped);
        if (!$record) return null;
        return DB::transaction(function () use ($record, $projectId, $sourceId) {
            $record = MetaAdRecord::lockForUpdate()->findOrFail($record->id);
            if ($record->crmCampaign) return $record->crmCampaign;
            if (!$projectId) return null;
            // Names can be identical across accounts; remote identity owns this mapping.
            $campaign = \App\Models\Campaign::create(['project_id' => $projectId, 'lead_source_id' => $sourceId,
                'name' => $record->name, 'status' => 'active']);
            $record->update(['crm_campaign_id' => $campaign->id]);
            return $campaign;
        });
    }

    public function audit(MetaAdAccount $account, string $action, array $details = []): void
    {
        \Illuminate\Support\Facades\Log::log(str_contains($action, 'failed') ? 'warning' : 'info',
            'Meta Ad Account: '.$action, $details + ['connection_id' => $account->id, 'ad_account_id' => $account->ad_account_id]);
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'meta.ad_account.'.$action,
            'subject_type' => MetaAdAccount::class, 'subject_id' => $account->id, 'to_value' => $details + ['ad_account_id' => $account->ad_account_id],
            'context' => ['category' => 'integration', 'status' => str_contains($action, 'failed') ? 'failure' : 'success'], 'created_at' => now()]);
    }
}
