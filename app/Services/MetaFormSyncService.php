<?php

namespace App\Services;

use App\Models\{AuditLog, LeadFormMapping, PortalAccount};
use Illuminate\Support\Facades\{Cache, DB, Log};
use Illuminate\Support\Str;

/** Shared Page form discovery for the settings button and scheduled lead sync. */
class MetaFormSyncService
{
    public function fetch(PortalAccount $account, ?string $token = null, string $origin = 'settings'): array
    {
        $pageId = $account->getCredential('page_id');
        $token ??= $account->getCredential('access_token');
        $result = ['run_id' => (string) Str::uuid(), 'origin' => $origin, 'page_id' => $pageId, 'reason' => null,
            'status' => 'failed', 'pages' => 0, 'fetched' => 0, 'new' => 0, 'updated' => 0,
            'saved' => 0, 'message' => '', 'forms' => [], 'http_status' => null, 'meta_code' => null];
        $lock = Cache::lock('meta-form-discovery-'.$account->id, 120);
        if (!$lock->get()) {
            $result['reason'] = 'already_running';
            $result['message'] = 'Form discovery is already running for this Page. Try again after it finishes.';
            return $this->finish($account, $result);
        }
        try {
            if ($account->type !== 'meta' || !$pageId || !$token) {
                $result['reason'] = 'missing_configuration';
                $result['message'] = 'Save a Facebook Page ID and Page Access Token for this connection before fetching forms.';
                return $this->finish($account, $result);
            }
            $graph = app(MetaAdAccountService::class);
            $after = null;
            $seen = [];
            $forms = [];
            do {
                $response = $graph->get($pageId.'/leadgen_forms', $token, [
                    'fields' => 'id,name,status,created_time,questions,locale,leads_count,expired_leads_count', 'limit' => 100,
                ] + ($after ? ['after' => $after] : []));
                if (!isset($response['data']) || !is_array($response['data'])) {
                    throw new \DomainException('invalid_response');
                }
                $result['pages']++;
                foreach ($response['data'] as $form) {
                    if (!is_array($form) || empty($form['id'])) throw new \DomainException('invalid_response');
                    $id = (string) $form['id'];
                    if (isset($forms[$id])) continue;
                    $metadata = [
                        'form_name' => trim((string) ($form['name'] ?? '')) ?: 'Lead Form #'.$id,
                        'page_id' => $pageId,
                        'status' => strtolower($form['status'] ?? 'active'),
                    ];
                    if (isset($form['questions'])) {
                        $metadata['questions'] = $form['questions'];
                    }
                    if ($created = LeadFormMapping::parseMetaCreatedTime($form['created_time'] ?? null)) {
                        $metadata['meta_created_at'] = $created;
                    }
                    // Only remote metadata is refreshed. Never overwrite administrator routing.
                    $mapping = DB::transaction(fn () => LeadFormMapping::updateOrCreate([
                        'portal_account_id' => $account->id, 'form_id' => $id,
                    ], $metadata));
                    if ($mapping->wasRecentlyCreated) $result['new']++;
                    elseif ($mapping->wasChanged()) $result['updated']++;
                    $forms[$id] = [
                        'id' => $id,
                        'name' => $metadata['form_name'],
                        'status' => strtoupper($metadata['status']),
                        'created_time' => $mapping->meta_created_at?->toIso8601String(),
                        'questions_count' => is_array($form['questions'] ?? null) ? count($form['questions']) : 0,
                    ];
                    $result['forms'] = array_values($forms);
                    $result['fetched'] = count($forms);
                }
                Log::info('Meta Page forms: page fetched', ['run_id' => $result['run_id'], 'connection_id' => $account->id,
                    'page_id' => $pageId, 'page_number' => $result['pages'], 'returned' => count($response['data'])]);
                // A short or empty page can still have a next page. A terminal cursor alone is not next.
                $after = $graph->nextCursor($response);
                if ($after && isset($seen[$after])) throw new \DomainException('pagination_stalled');
                if ($after) $seen[$after] = true;
                if ($after && $result['pages'] >= 500) throw new \DomainException('safety_limit');
            } while ($after);
            $result['status'] = 'completed';
            $result['message'] = $result['fetched'] === 0
                ? 'Meta returned no Page lead forms. Check the selected Page and whether the ad uses a published Instant Form.'
                : 'Page lead forms fetched successfully. Campaigns and ad sets are synchronized separately under Connected Ad Accounts.';
        } catch (\Throwable $e) {
            $result['status'] = $result['fetched'] ? 'partial' : 'failed';
            $result['message'] = $e instanceof MetaGraphException
                ? ($e->expired ? 'Page token expired or was revoked. Reconnect this Page.' : ($e->retryable
                    ? 'Meta is temporarily unavailable or rate limited. Retry later; saved mappings are preserved.'
                    : 'Meta denied Page access. Check the selected Page, Page token and lead-form permissions.'))
                : 'Form discovery did not finish. Check the Page connection and retry; saved mappings are preserved.';
            if ($e instanceof MetaGraphException) {
                $result['reason'] = $e->expired ? 'token_expired' : ($e->retryable ? 'rate_limited_or_unavailable' : 'permission_denied');
                $result['http_status'] = $e->httpStatus;
                $result['meta_code'] = $e->metaCode;
            } else {
                $result['reason'] = $e instanceof \DomainException ? $e->getMessage() : 'connection_or_response_error';
                if ($result['reason'] === 'pagination_stalled') $result['message'] = 'Meta repeated a pagination cursor. Discovery stopped safely; fetched forms were saved. Retry the Page fetch.';
                if ($result['reason'] === 'safety_limit') $result['message'] = 'Discovery reached the pagination safety limit. The saved catalogue is incomplete.';
            }
            // Never log raw provider messages, exception URLs or tokens.
        } finally {
            $lock->release();
        }
        return $this->finish($account, $result);
    }

    private function finish(PortalAccount $account, array $result): array
    {
        $result['saved'] = $account->formMappings()->count();
        $summary = array_diff_key($result, ['forms' => true]);
        $log = AuditLog::create(['user_id' => auth()->id(), 'action' => 'meta.forms.'.$result['status'],
            'subject_type' => PortalAccount::class, 'subject_id' => $account->id, 'to_value' => $summary,
            'context' => ['category' => 'integration', 'status' => $result['status'] === 'completed' ? 'success' : 'failure'],
            'created_at' => now()]);
        Log::log($result['status'] === 'completed' ? 'info' : 'warning', 'Meta Page forms: discovery result', $summary);
        $result['log_id'] = $log->id;
        return $result;
    }
}
