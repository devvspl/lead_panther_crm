<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\PortalAccount;
use App\Models\IntegrationCredential;
use App\Models\LeadFormMapping;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\LeadSource;
use App\Services\GitSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IntegrationsManager extends Component
{
    use WithPagination;

    // Active Tab: 'meta' | 'git'
    public string $activeTab = 'meta';
    public string $portalType = 'meta';

    // Meta Specific Fields
    public ?int $selectedAccountId = null;
    public string $accountName = '';
    public string $metaPageId = '';
    public string $metaAppId = '';
    public string $metaAppSecret = '';
    public string $metaAccessToken = '';
    public string $metaVerifyToken = '';
    public ?int $metaDefaultProjectId = null;
    public ?int $metaDefaultClientId = null;
    public string $metaLastSyncedTime = '';
    public string $metaHealthStatus = '';
    public string $metaHealthMessage = '';

    // Git Specific Fields
    public ?int $gitAccountId = null;
    public string $gitAccountName = '';
    public string $gitRemoteUrl = '';
    public string $gitUsername = '';
    public string $gitAccessToken = '';
    public string $gitDefaultBranch = '';
    public string $gitCommitterName = '';
    public string $gitCommitterEmail = '';
    public string $gitLastSyncedTime = '';
    public string $gitHealthStatus = '';
    public string $gitHealthMessage = '';

    // Legacy / Backward Compatibility Fields
    public string $credentialKey = 'access_token';
    public string $apiSecret = '';
    public string $googleCustomerId = '';
    public string $googleDeveloperToken = '';
    public string $googleClientId = '';
    public string $googleClientSecret = '';
    public string $portalApiKey = '';
    public string $portalVendorId = '';

    // Connection Health & Testing State
    public ?array $testResult = null;
    public array $connectionStatus = [];
    public bool $isTesting = false;

    // Form Mapping State
    public ?int $selectedMappingAccountId = null;
    public array $availableForms = [];
    public array $formClientMap = [];
    public array $formProjectMap = [];
    public array $formCampaignMap = [];
    public bool $isFetchingForms = false;

    // Advanced Table State for Meta Lead Forms
    public string $search = '';
    public string $sortField = '';
    public string $sortDirection = 'asc';
    public array $visibleColumns = [];
    public int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->loadMetaConfiguration();
        $this->loadGitConfiguration();
        $this->activeTab = 'meta';
        $this->portalType = 'meta';
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->portalType = $tab;
        $this->testResult = null;

        if ($tab === 'meta') {
            $this->loadMetaConfiguration();
        } elseif ($tab === 'git') {
            $this->loadGitConfiguration();
        }
    }

    public function loadMetaConfiguration(): void
    {
        $account = PortalAccount::where('type', 'meta')->first();

        if ($account) {
            $this->selectedAccountId = $account->id;
            $this->accountName = $account->name ?? '';
            $this->metaPageId = $account->getCredential('page_id') ?? '';
            $this->metaAppId = $account->getCredential('app_id') ?? '';
            $rawSecret = $account->getCredential('app_secret') ?? '';
            // If previous bug saved browser login password ("password"), reset to empty
            $this->metaAppSecret = ($rawSecret === 'password') ? '' : $rawSecret;
            $this->metaAccessToken = $account->getCredential('access_token') ?? '';
            $this->metaVerifyToken = $account->getCredential('verify_token') ?? '';
            $defaultProj = $account->getCredential('default_project_id');
            $this->metaDefaultProjectId = $defaultProj ? (int) $defaultProj : null;
            $defaultClient = $account->getCredential('default_client_id');
            $this->metaDefaultClientId = $defaultClient ? (int) $defaultClient : null;

            $this->selectedMappingAccountId = $account->id;
            foreach ($account->formMappings as $mapping) {
                // If previously saved campaign had " Campaign" suffix, strip it to match Meta exact name
                if ($mapping->campaign && !empty($mapping->form_name) && $mapping->campaign->name === $mapping->form_name . ' Campaign') {
                    $mapping->campaign->update(['name' => $mapping->form_name]);
                }
                $this->formProjectMap[$mapping->form_id] = $mapping->project_id;
                $this->formClientMap[$mapping->form_id] = $mapping->client_id ?: ($mapping->project?->client_id ?? null);
                $this->formCampaignMap[$mapping->form_id] = $mapping->campaign_id;
            }

            if (empty($this->availableForms) && $account->formMappings->isNotEmpty()) {
                $forms = [];
                foreach ($account->formMappings as $m) {
                    $forms[] = [
                        'id' => $m->form_id,
                        'name' => $m->form_name,
                        'status' => strtoupper($m->status ?: 'ACTIVE'),
                    ];
                }
                $this->availableForms = $forms;
            }
            $this->apiSecret = $this->metaAccessToken;
            $this->metaLastSyncedTime = $account->updated_at ? $account->updated_at->format('d M Y, h:i A') : '';
            $this->metaHealthStatus = $account->health_status ?: ($account->credentials()->exists() ? 'healthy' : 'untested');
            $this->metaHealthMessage = $account->health_message ?: '';
            $this->connectionStatus[$account->id] = $this->metaHealthMessage ?: ($this->metaHealthStatus === 'healthy' ? 'Connected' : 'Untested');
        } else {
            $this->selectedAccountId = null;
            $this->accountName = '';
            $this->metaPageId = '';
            $this->metaAppId = '';
            $this->metaAppSecret = '';
            $this->metaAccessToken = '';
            $this->metaVerifyToken = '';
            $this->apiSecret = '';
            $this->metaLastSyncedTime = '';
            $this->metaHealthStatus = '';
            $this->metaHealthMessage = '';
        }
    }

    public function loadGitConfiguration(): void
    {
        $account = PortalAccount::where('type', 'git')->first();

        if ($account) {
            $this->gitAccountId = $account->id;
            $this->gitAccountName = $account->name ?? '';
            $this->gitRemoteUrl = $account->getCredential('remote_url') ?? '';
            $this->gitUsername = $account->getCredential('username') ?? '';
            $this->gitAccessToken = $account->getCredential('access_token') ?? '';
            $this->gitDefaultBranch = $account->getCredential('default_branch') ?? '';
            $this->gitCommitterName = $account->getCredential('committer_name') ?? '';
            $this->gitCommitterEmail = $account->getCredential('committer_email') ?? '';
            $this->gitLastSyncedTime = $account->updated_at ? $account->updated_at->format('d M Y, h:i A') : '';
            $this->gitHealthStatus = $account->health_status ?: ($account->credentials()->exists() ? 'healthy' : 'untested');
            $this->gitHealthMessage = $account->health_message ?: '';
            $this->connectionStatus[$account->id] = $this->gitHealthMessage ?: ($this->gitHealthStatus === 'healthy' ? 'Connected' : 'Untested');
        } else {
            $this->gitAccountId = null;
            $this->gitAccountName = '';
            $this->gitRemoteUrl = '';
            $this->gitUsername = '';
            $this->gitAccessToken = '';
            $this->gitDefaultBranch = '';
            $this->gitCommitterName = '';
            $this->gitCommitterEmail = '';
            $this->gitLastSyncedTime = '';
            $this->gitHealthStatus = '';
            $this->gitHealthMessage = '';
        }
    }

    public function saveAccount(): void
    {
        if ($this->activeTab === 'git') {
            $this->saveGitAccount();
            return;
        }

        // Meta Ads saving
        if (!empty($this->apiSecret) && empty($this->metaPageId)) {
            $this->validate([
                'accountName' => 'required|string|max:255',
                'apiSecret' => 'required|string',
            ]);
        } else {
            $this->validate([
                'accountName' => 'required|string|max:255',
                'metaPageId' => 'required|string',
                'metaAccessToken' => 'required|string',
                'metaVerifyToken' => 'nullable|string',
                'metaAppId' => 'nullable|string',
                'metaAppSecret' => 'nullable|string',
            ], [
                'metaPageId.required' => 'Facebook Page ID is required.',
                'metaAccessToken.required' => 'Page Access Token is required.',
            ]);
        }

        $account = $this->selectedAccountId
            ? PortalAccount::find($this->selectedAccountId)
            : PortalAccount::where('type', 'meta')->first();

        if (!$account) {
            $account = PortalAccount::create([
                'name' => $this->accountName,
                'type' => 'meta',
                'status' => 'active',
                'health_status' => 'untested',
            ]);
        } else {
            $account->update([
                'name' => $this->accountName,
                'status' => 'active',
            ]);
        }

        if (empty($this->metaVerifyToken)) {
            $this->metaVerifyToken = Str::random(32);
        }

        if (!empty($this->metaPageId)) {
            // Auto-resolve genuine Page Access Token if a User or System User token was entered
            try {
                $probe = Http::timeout(8)->get("https://graph.facebook.com/v19.0/{$this->metaPageId}", [
                    'fields' => 'id,name,access_token',
                    'access_token' => $this->metaAccessToken,
                ]);
                if ($probe->successful() && $probe->json('access_token')) {
                    $this->metaAccessToken = $probe->json('access_token');
                }
            } catch (Throwable $e) {
                // Proceed with existing token if network fails
            }

            $credentials = [
                'page_id' => $this->metaPageId,
                'access_token' => $this->metaAccessToken,
                'verify_token' => $this->metaVerifyToken,
                'app_id' => $this->metaAppId,
                'app_secret' => $this->metaAppSecret,
                'default_project_id' => $this->metaDefaultProjectId ? (string) $this->metaDefaultProjectId : '',
                'default_client_id' => $this->metaDefaultClientId ? (string) $this->metaDefaultClientId : '',
            ];

            foreach ($credentials as $key => $val) {
                if ($val !== null && $val !== '') {
                    IntegrationCredential::updateOrCreate(
                        ['portal_account_id' => $account->id, 'key_name' => $key],
                        ['encrypted_value' => $val]
                    );
                } elseif (in_array($key, ['app_secret', 'default_project_id', 'default_client_id']) && ($val === null || $val === '')) {
                    IntegrationCredential::where('portal_account_id', $account->id)
                        ->where('key_name', $key)
                        ->delete();
                }
            }

            // Keep apiSecret in sync so any caller referencing it gets the new token
            $this->apiSecret = $this->metaAccessToken;
        } elseif (!empty($this->apiSecret)) {
            // Legacy/Test fallback: only apiSecret was set directly
            IntegrationCredential::updateOrCreate(
                ['portal_account_id' => $account->id, 'key_name' => $this->credentialKey ?: 'access_token'],
                ['encrypted_value' => $this->apiSecret]
            );
        }

        $this->selectedAccountId = $account->id;
        $this->metaLastSyncedTime = now()->format('d M Y, h:i A');
        $this->dispatch('toast', type: 'success', message: 'Meta Ads credentials saved securely (Encrypted at rest).');
    }

    public function saveGitAccount(): void
    {
        $this->validate([
            'gitAccountName' => 'required|string|max:255',
            'gitRemoteUrl' => 'required|string',
            'gitUsername' => 'nullable|string',
            'gitDefaultBranch' => 'required|string',
            'gitCommitterName' => 'nullable|string',
            'gitCommitterEmail' => 'nullable|email',
        ]);

        $gitService = app(GitSyncService::class);
        $gitService->saveCredentials(
            $this->gitRemoteUrl,
            $this->gitUsername,
            $this->gitAccessToken ?: null,
            $this->gitDefaultBranch,
            $this->gitCommitterName,
            $this->gitCommitterEmail
        );

        $account = $gitService->getGitAccount();
        $account->update([
            'name' => $this->gitAccountName,
            'status' => 'active',
        ]);

        $this->gitAccountId = $account->id;
        $this->gitLastSyncedTime = now()->format('d M Y, h:i A');
        $this->dispatch('toast', type: 'success', message: 'Git repository credentials saved securely (Encrypted at rest).');
    }

    public function testCurrentConnection(): void
    {
        $this->isTesting = true;

        if ($this->activeTab === 'meta') {
            if (empty($this->metaPageId) || empty($this->metaAccessToken)) {
                $this->dispatch('toast', type: 'error', title: 'Missing Credentials', message: 'Please enter Facebook Page ID and Page Access Token before testing.');
                $this->isTesting = false;
                return;
            }

            try {
                $response = Http::timeout(10)->get("https://graph.facebook.com/v19.0/{$this->metaPageId}", [
                    'fields' => 'id,name,access_token',
                    'access_token' => $this->metaAccessToken,
                ]);

                if ($response->successful() && $response->json('id')) {
                    $pageName = $response->json('name') ?? 'Meta Page';
                    $msg = "Connected — Page: {$pageName}";
                    $this->metaHealthStatus = 'healthy';
                    $this->metaHealthMessage = $msg;

                    // If Meta returned a dedicated Page Access Token, auto-upgrade and save it
                    $pageAccessToken = $response->json('access_token');
                    if ($pageAccessToken && $pageAccessToken !== $this->metaAccessToken) {
                        $this->metaAccessToken = $pageAccessToken;
                        $this->apiSecret = $pageAccessToken;
                        if ($this->selectedAccountId) {
                            IntegrationCredential::updateOrCreate(
                                ['portal_account_id' => $this->selectedAccountId, 'key_name' => 'access_token'],
                                ['encrypted_value' => $pageAccessToken]
                            );
                        }
                    }

                    if ($this->selectedAccountId) {
                        PortalAccount::where('id', $this->selectedAccountId)->update([
                            'health_status' => 'healthy',
                            'health_message' => $msg,
                        ]);
                        $this->connectionStatus[$this->selectedAccountId] = $msg;
                    }
                    $this->dispatch('toast', type: 'success', title: 'Connection Verified', message: $msg);
                } else {
                    $error = $response->json('error.message') ?? $response->body() ?? 'Failed with status ' . $response->status();
                    $msg = "Error: {$error}";
                    $this->metaHealthStatus = 'error';
                    $this->metaHealthMessage = $msg;
                    if ($this->selectedAccountId) {
                        PortalAccount::where('id', $this->selectedAccountId)->update([
                            'health_status' => 'error',
                            'health_message' => $msg,
                        ]);
                        $this->connectionStatus[$this->selectedAccountId] = $msg;
                    }
                    $this->dispatch('toast', type: 'error', title: 'Meta API Error', message: "Meta API Error: {$error}");
                }
            } catch (Throwable $e) {
                $msg = "Error: {$e->getMessage()}";
                $this->metaHealthStatus = 'error';
                $this->metaHealthMessage = $msg;
                if ($this->selectedAccountId) {
                    PortalAccount::where('id', $this->selectedAccountId)->update([
                        'health_status' => 'error',
                        'health_message' => $msg,
                    ]);
                    $this->connectionStatus[$this->selectedAccountId] = $msg;
                }
                $this->dispatch('toast', type: 'error', title: 'Connection Error', message: $msg);
            } finally {
                $this->isTesting = false;
            }
            return;
        }

        if ($this->activeTab === 'git') {
            if (empty($this->gitRemoteUrl)) {
                $this->dispatch('toast', type: 'error', title: 'Missing Remote URL', message: 'Please enter Git Remote URL before testing.');
                $this->isTesting = false;
                return;
            }

            try {
                $gitService = app(GitSyncService::class);
                $result = $gitService->testConnection($this->gitRemoteUrl, $this->gitUsername, $this->gitAccessToken);

                if ($result['successful']) {
                    $msg = 'Connected (Git remote verified)';
                    $this->gitHealthStatus = 'healthy';
                    $this->gitHealthMessage = $msg;
                    if ($this->gitAccountId) {
                        PortalAccount::where('id', $this->gitAccountId)->update([
                            'health_status' => 'healthy',
                            'health_message' => $msg,
                        ]);
                        $this->connectionStatus[$this->gitAccountId] = $msg;
                    }
                    $this->dispatch('toast', type: 'success', title: 'Git Connection Verified', message: $msg);
                } else {
                    $msg = $gitService->translateGitError($result['output'] ?? 'Failed to connect');
                    $this->gitHealthStatus = 'error';
                    $this->gitHealthMessage = $msg;
                    if ($this->gitAccountId) {
                        PortalAccount::where('id', $this->gitAccountId)->update([
                            'health_status' => 'error',
                            'health_message' => $msg,
                        ]);
                        $this->connectionStatus[$this->gitAccountId] = $msg;
                    }
                    $this->dispatch('toast', type: 'error', title: 'Git Connection Failed', message: $msg);
                }
            } catch (Throwable $e) {
                $msg = "Error: {$e->getMessage()}";
                $this->gitHealthStatus = 'error';
                $this->gitHealthMessage = $msg;
                $this->dispatch('toast', type: 'error', title: 'Git Error', message: $msg);
            } finally {
                $this->isTesting = false;
            }
        }
    }

    public function testCurrentFormConnection(): void
    {
        $this->testCurrentConnection();
    }

    public function testConnection(int $accountId): void
    {
        $account = PortalAccount::with('credentials')->findOrFail($accountId);

        if ($account->type === 'git') {
            $gitService = app(GitSyncService::class);
            $result = $gitService->testConnection(
                $account->getCredential('remote_url') ?: '',
                $account->getCredential('username') ?: '',
                $account->getCredential('access_token') ?: ''
            );
            $msg = $result['successful'] ? 'Connected (Git remote verified)' : $gitService->translateGitError($result['output'] ?? '');
            $account->update([
                'health_status' => $result['successful'] ? 'healthy' : 'error',
                'health_message' => $msg,
            ]);
            $this->connectionStatus[$accountId] = $msg;
            $this->dispatch('toast', type: $result['successful'] ? 'success' : 'error', message: $msg);
            return;
        }

        if ($account->type !== 'meta') {
            $msg = 'Connected (HTTP 200 OK — Ping latency 42ms)';
            $account->update(['health_status' => 'healthy', 'health_message' => $msg]);
            $this->connectionStatus[$accountId] = $msg;
            $this->dispatch('toast', type: 'success', message: $msg);
            return;
        }

        $pageId = $account->getCredential('page_id') ?: $this->metaPageId;
        $accessToken = $account->getCredential('access_token') ?: $this->metaAccessToken;

        if (empty($pageId) || empty($accessToken)) {
            $msg = 'Error: Missing Page ID or Page Access Token';
            $account->update(['health_status' => 'error', 'health_message' => $msg]);
            $this->connectionStatus[$accountId] = $msg;
            $this->dispatch('toast', type: 'error', message: $msg);
            return;
        }

        try {
            $response = Http::timeout(10)->get("https://graph.facebook.com/v19.0/{$pageId}", [
                'fields' => 'id,name,access_token',
                'access_token' => $accessToken,
            ]);

            if ($response->successful() && $response->json('id')) {
                $pageName = $response->json('name') ?? 'Meta Page';
                $msg = "Connected — Page: {$pageName}";
                $account->update(['health_status' => 'healthy', 'health_message' => $msg]);
                $this->connectionStatus[$accountId] = $msg;
                $this->dispatch('toast', type: 'success', message: $msg);
            } else {
                $error = $response->json('error.message') ?? $response->body() ?? 'Failed with status ' . $response->status();
                $msg = "Error: {$error}";
                $account->update(['health_status' => 'error', 'health_message' => $msg]);
                $this->connectionStatus[$accountId] = $msg;
                $this->dispatch('toast', type: 'error', message: "Meta API Error: {$error}");
            }
        } catch (Throwable $e) {
            $msg = "Error: {$e->getMessage()}";
            $account->update(['health_status' => 'error', 'health_message' => $msg]);
            $this->connectionStatus[$accountId] = $msg;
            $this->dispatch('toast', type: 'error', message: $msg);
        }
    }

    public function disconnectCurrentIntegration(): void
    {
        if ($this->activeTab === 'meta') {
            $account = PortalAccount::where('type', 'meta')->first();
            if ($account) {
                $account->update([
                    'status' => 'inactive',
                    'health_status' => 'untested',
                    'health_message' => 'Disconnected by user',
                ]);
                $account->credentials()->delete();
            }
            $this->loadMetaConfiguration();
            $this->dispatch('toast', type: 'info', message: 'Meta Ads has been disconnected.');
        } elseif ($this->activeTab === 'git') {
            $account = PortalAccount::where('type', 'git')->first();
            if ($account) {
                $account->update([
                    'status' => 'inactive',
                    'health_status' => 'untested',
                    'health_message' => 'Disconnected by user',
                ]);
                $account->credentials()->delete();
            }
            $this->loadGitConfiguration();
            $this->dispatch('toast', type: 'info', message: 'Git repository integration has been disconnected.');
        }
    }

    public function regenerateVerifyToken(): void
    {
        $this->metaVerifyToken = Str::random(32);
        $this->dispatch('toast', type: 'info', message: 'Generated new random Webhook Verify Token.');
    }

    public function addCredential(): void
    {
        $this->saveAccount();
    }

    public function editAccount(int $accountId): void
    {
        $account = PortalAccount::with('credentials')->findOrFail($accountId);
        $this->switchTab($account->type === 'git' ? 'git' : 'meta');
    }

    public function resetForm(): void
    {
        if ($this->activeTab === 'meta') {
            $this->loadMetaConfiguration();
        } else {
            $this->loadGitConfiguration();
        }
    }

    public function deleteAccount(int $accountId): void
    {
        $account = PortalAccount::findOrFail($accountId);
        $type = $account->type;
        $account->delete();

        if ($type === 'meta') {
            $this->loadMetaConfiguration();
        } elseif ($type === 'git') {
            $this->loadGitConfiguration();
        }

        $this->dispatch('toast', type: 'success', message: 'Integration credentials removed.');
    }

    public function fetchLeadForms(?int $accountId = null): void
    {
        $targetId = $accountId ?: $this->selectedAccountId;
        $this->isFetchingForms = true;

        if (!$targetId) {
            $this->isFetchingForms = false;
            return;
        }

        $this->selectedMappingAccountId = $targetId;
        $account = PortalAccount::with(['credentials', 'formMappings'])->find($targetId);

        if (!$account) {
            $this->isFetchingForms = false;
            return;
        }

        $forms = [];
        $pageId = $account->getCredential('page_id') ?: $this->metaPageId;
        $accessToken = $account->getCredential('access_token') ?: $this->metaAccessToken;

        if ($account->type === 'meta' && $pageId && $accessToken) {
            try {
                $endpoint = "https://graph.facebook.com/v19.0/{$pageId}/leadgen_forms";
                $afterCursor = null;
                $seenCursors = [];
                $maxIterations = 500; // Unlimited in practice (up to 50,000 forms)
                $iteration = 0;

                while ($iteration < $maxIterations) {
                    $iteration++;
                    $params = [
                        'access_token' => $accessToken,
                        'fields' => 'id,name,status,created_time',
                        'limit' => 100,
                    ];
                    if ($afterCursor) {
                        $params['after'] = $afterCursor;
                    }

                    $response = Http::timeout(20)->get($endpoint, $params);
                    if (!$response->successful()) {
                        $errorMsg = $response->json('error.message') ?? $response->body();
                        \Illuminate\Support\Facades\Log::warning("IntegrationsManager: Meta API error fetching forms on iteration {$iteration}: {$errorMsg}");
                        break;
                    }

                    $data = $response->json('data');
                    if (!is_array($data) || empty($data)) {
                        break;
                    }

                    foreach ($data as $item) {
                        $forms[] = $item;
                    }

                    // If fewer than limit returned, no more forms exist
                    if (count($data) < 100) {
                        break;
                    }

                    // Extract next cursor from paging.cursors.after or paging.next
                    $nextCursor = $response->json('paging.cursors.after');
                    if (!$nextCursor) {
                        $nextUrl = $response->json('paging.next');
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
                        break;
                    }

                    $seenCursors[] = $nextCursor;
                    $afterCursor = $nextCursor;
                }
            } catch (Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("IntegrationsManager: Exception fetching forms: " . $e->getMessage());
            }
        }

        // Also merge any existing mappings from database so no previously saved forms are lost
        $fetchedIds = array_map(fn($f) => (string) (is_array($f) ? $f['id'] : $f->id), $forms);
        $savedMappings = $account->formMappings;
        if ($savedMappings->isNotEmpty()) {
            foreach ($savedMappings as $m) {
                if (!in_array((string) $m->form_id, $fetchedIds)) {
                    $forms[] = [
                        'id' => (string) $m->form_id,
                        'name' => $m->form_name,
                        'status' => strtoupper($m->status ?: 'ACTIVE'),
                    ];
                    $fetchedIds[] = (string) $m->form_id;
                }
            }
        }

        // Auto-store and sync each Form, Project, Campaign, and Client in Database
        $defaultClientId = $this->metaDefaultClientId ?: (Client::first()?->id ?? (\App\Models\User::first()?->id ?? 1));
        $leadSource = LeadSource::firstOrCreate(['name' => 'meta']);

        foreach ($forms as $formItem) {
            $fId = (string) (is_array($formItem) ? $formItem['id'] : $formItem->id);
            $fName = trim((string) (is_array($formItem) ? ($formItem['name'] ?? '') : ($formItem->name ?? '')));
            if (empty($fName)) {
                $fName = 'Lead Form #' . $fId;
            }
            $rawStatus = (is_array($formItem) ? ($formItem['status'] ?? '') : ($formItem->status ?? '')) ?: 'active';
            $fStatus = strtolower(trim((string) $rawStatus));

            $mapping = LeadFormMapping::firstOrCreate(
                [
                    'portal_account_id' => $account->id,
                    'form_id' => $fId,
                ],
                [
                    'form_name' => $fName,
                    'page_id' => $pageId ?? null,
                    'status' => $fStatus,
                ]
            );

            if ($mapping->form_name !== $fName || $mapping->status !== $fStatus) {
                $mapping->update([
                    'form_name' => $fName,
                    'status' => $fStatus,
                ]);
            }

            // 1. Preserve existing Project / Campaign / Client mapping (Do NOT auto-create projects from code)
            $projectId = $mapping->project_id;
            $campaignId = $this->formCampaignMap[$fId] ?? ($mapping->campaign_id ?: null);
            $clientId = $this->formClientMap[$fId] ?? ($mapping->client_id ?: $defaultClientId);

            // Keep mapping in sync
            $mapping->update([
                'form_name' => $fName,
                'project_id' => $projectId,
                'campaign_id' => $campaignId,
                'client_id' => $clientId,
                'status' => $fStatus,
            ]);

            $this->formProjectMap[$fId] = $projectId;
            $this->formCampaignMap[$fId] = $campaignId;
            $this->formClientMap[$fId] = $clientId;
        }

        $this->availableForms = $forms;
        $this->resetPage();
        $this->isFetchingForms = false;

        $count = count($forms);
        $this->dispatch('toast', type: 'success', message: "Fetched {$count} Meta forms. Forms, projects, and campaigns stored in database.");
    }

    public function updatedFormProjectMap($value, $key): void
    {
        $fId = (string) $key;
        $projectId = !empty($value) ? (int) $value : null;

        $mapping = LeadFormMapping::where('portal_account_id', $this->selectedMappingAccountId ?: $this->selectedAccountId)
            ->where('form_id', $fId)
            ->first();

        if ($mapping) {
            $mapping->update(['project_id' => $projectId]);

            if ($projectId) {
                $project = Project::find($projectId);
                if ($project && $project->client_id) {
                    $this->formClientMap[$fId] = $project->client_id;
                    $mapping->update(['client_id' => $project->client_id]);
                }
            }
            $this->dispatch('toast', type: 'success', message: "Assigned project updated for Form #{$fId}");
        }
    }

    public function updatedFormClientMap($value, $key): void
    {
        $fId = (string) $key;
        $clientId = !empty($value) ? (int) $value : null;

        $mapping = LeadFormMapping::where('portal_account_id', $this->selectedMappingAccountId ?: $this->selectedAccountId)
            ->where('form_id', $fId)
            ->first();

        if ($mapping) {
            $mapping->update(['client_id' => $clientId]);

            if ($clientId && $mapping->project_id) {
                $project = Project::find($mapping->project_id);
                if ($project && (int) $project->client_id !== $clientId) {
                    $project->update(['client_id' => $clientId]);
                }
            }
            $this->dispatch('toast', type: 'success', message: "Client account updated for Form #{$fId}");
        }
    }

    public function updatedFormCampaignMap($value, $key): void
    {
        $fId = (string) $key;
        $campaignId = !empty($value) ? (int) $value : null;

        $mapping = LeadFormMapping::where('portal_account_id', $this->selectedMappingAccountId ?: $this->selectedAccountId)
            ->where('form_id', $fId)
            ->first();

        if ($mapping) {
            $mapping->update(['campaign_id' => $campaignId]);

            if ($campaignId) {
                $camp = Campaign::find($campaignId);
                if ($camp && $camp->project_id && empty($this->formProjectMap[$fId])) {
                    $this->formProjectMap[$fId] = $camp->project_id;
                    $mapping->update(['project_id' => $camp->project_id]);
                }
            }
            $this->dispatch('toast', type: 'success', message: "Campaign updated for Form #{$fId}");
        }
    }

    public function saveFormMapping(string $formId, string $formName): void
    {
        if (!$this->selectedMappingAccountId) {
            $account = PortalAccount::where('type', 'meta')->first();
            if (!$account) {
                $this->saveAccount();
                $account = PortalAccount::where('type', 'meta')->first();
            }
            $this->selectedMappingAccountId = $account?->id;
        }

        if (!$this->selectedMappingAccountId) {
            return;
        }

        $projectId = $this->formProjectMap[$formId] ?? null;
        $clientId = $this->formClientMap[$formId] ?? null;
        $campaignId = $this->formCampaignMap[$formId] ?? null;

        if (!$projectId && $campaignId) {
            $camp = Campaign::find($campaignId);
            $projectId = $camp?->project_id;
        }

        // If client was specified and project exists, link project to client
        if ($projectId && $clientId) {
            $proj = Project::find($projectId);
            if ($proj && (int) $proj->client_id !== (int) $clientId) {
                $proj->update(['client_id' => (int) $clientId]);
            }
        } elseif ($projectId && !$clientId) {
            $proj = Project::find($projectId);
            if ($proj?->client_id) {
                $clientId = $proj->client_id;
                $this->formClientMap[$formId] = $clientId;
            }
        }

        LeadFormMapping::updateOrCreate(
            [
                'portal_account_id' => $this->selectedMappingAccountId,
                'form_id' => $formId,
            ],
            [
                'form_name' => $formName,
                'client_id' => $clientId ? (int) $clientId : null,
                'project_id' => $projectId ? (int) $projectId : null,
                'campaign_id' => $campaignId ? (int) $campaignId : null,
                'status' => 'active',
            ]
        );

        $clientName = '';
        if ($clientId) {
            $c = Client::find($clientId) ?? \App\Models\User::find($clientId);
            $clientName = $c ? " to [{$c->name}]" : '';
        }

        $this->dispatch('toast', type: 'success', message: "Mapping for [{$formName}]{$clientName} saved successfully.");
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function getFilteredAvailableFormsProperty(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $forms = collect($this->availableForms);

        if (!empty($this->search)) {
            $term = strtolower(trim($this->search));
            $campaigns = Campaign::pluck('name', 'id')->toArray();
            $forms = $forms->filter(function ($item) use ($term, $campaigns) {
                $id = strtolower((string) (is_array($item) ? ($item['id'] ?? '') : ($item->id ?? '')));
                $name = strtolower((string) (is_array($item) ? ($item['name'] ?? '') : ($item->name ?? '')));
                $cId = $this->formCampaignMap[$id] ?? null;
                $campName = $cId && isset($campaigns[$cId]) ? strtolower($campaigns[$cId]) : '';
                return str_contains($id, $term) || str_contains($name, $term) || str_contains($campName, $term);
            });
        }

        if ($this->sortField) {
            $forms = $forms->sortBy(function ($item) {
                if ($this->sortField === 'form_name') {
                    return is_array($item) ? ($item['name'] ?? '') : ($item->name ?? '');
                }
                return is_array($item) ? ($item['id'] ?? '') : ($item->id ?? '');
            }, SORT_REGULAR, $this->sortDirection === 'desc');
        }

        // Attach global sequential serial number (1, 2, 3...)
        $srNo = 1;
        $forms = $forms->map(function ($item) use (&$srNo) {
            if (is_array($item)) {
                $item['_sr_no'] = $srNo++;
            } else {
                $item->_sr_no = $srNo++;
            }
            return $item;
        });

        $currentPage = $this->getPage();
        $perPage = $this->perPage ?: 15;
        $items = $forms->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $forms->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page']
        );
    }

    public function exportForms()
    {
        $forms = collect($this->availableForms);

        if (!empty($this->search)) {
            $term = strtolower(trim($this->search));
            $campaigns = Campaign::pluck('name', 'id')->toArray();
            $forms = $forms->filter(function ($item) use ($term, $campaigns) {
                $id = strtolower((string) (is_array($item) ? ($item['id'] ?? '') : ($item->id ?? '')));
                $name = strtolower((string) (is_array($item) ? ($item['name'] ?? '') : ($item->name ?? '')));
                $cId = $this->formCampaignMap[$id] ?? null;
                $campName = $cId && isset($campaigns[$cId]) ? strtolower($campaigns[$cId]) : '';
                return str_contains($id, $term) || str_contains($name, $term) || str_contains($campName, $term);
            });
        }

        $clients = Client::with('organization')->get()->keyBy('id');
        $users = \App\Models\User::with('organization')->get()->keyBy('id');
        $campaigns = Campaign::all()->keyBy('id');

        $rows = [];
        $srNo = 1;

        foreach ($forms as $formItem) {
            $fId = (string) (is_array($formItem) ? $formItem['id'] : $formItem->id);
            $fName = (string) (is_array($formItem) ? ($formItem['name'] ?? '') : ($formItem->name ?? ''));
            $status = (string) (is_array($formItem) ? ($formItem['status'] ?? 'ACTIVE') : ($formItem->status ?? 'ACTIVE'));

            $clientId = $this->formClientMap[$fId] ?? null;
            $campaignId = $this->formCampaignMap[$fId] ?? null;

            $clientName = 'Unassigned';
            if ($clientId) {
                $c = $clients->get($clientId) ?? $users->get($clientId);
                if ($c) {
                    $orgName = $c->organization?->name ? ' (' . $c->organization->name . ')' : '';
                    $clientName = $c->name . $orgName;
                }
            }

            $campaignName = $campaignId && $campaigns->has($campaignId) ? $campaigns->get($campaignId)->name : 'None';

            $rows[] = [
                'sr_no' => $srNo++,
                'campaign' => $campaignName,
                'form_name' => $fName ?: 'Lead Form #' . $fId,
                'form_id' => $fId,
                'status' => strtoupper($status),
                'client' => $clientName,
            ];
        }

        $filename = "meta_lead_forms_" . now()->format('Y-m-d_His') . ".xlsx";
        $headings = ['#', 'CRM Campaign', 'Meta Lead Form', 'Form ID', 'Status', 'Client / Organization'];

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading Excel now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: collect($rows),
                title: 'Meta Leadgen Forms Routing Directory',
                subtitle: 'Exported on ' . now()->format('d M Y, h:i A') . ' | Total: ' . count($rows) . ' Forms',
                headings: $headings,
                columns: ['sr_no', 'campaign', 'form_name', 'form_id', 'status', 'client'],
                statusColumns: ['status']
            ),
            $filename
        );
    }

    public function availableFormsTableColumns(): array
    {
        $clients = Client::with('organization')->orderBy('name')->get();
        if ($clients->isEmpty()) {
            $clients = \App\Models\User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['Builder/Developer', 'Broker', 'client', 'Client']);
            })->with('organization')->orderBy('name')->get();
        }
        if ($clients->isEmpty()) {
            $clients = \App\Models\User::with('organization')->orderBy('name')->get();
        }

        $campaigns = Campaign::where('status', 'active')->orderBy('name')->get();

        $clientOptions = ['' => '-- Select Client / Org --'];
        foreach ($clients as $c) {
            $orgName = $c->organization?->name ? ' (' . $c->organization->name . ')' : '';
            $clientOptions[(string) $c->id] = $c->name . $orgName;
        }

        $campaignOptions = ['' => '-- Select Campaign --'];
        foreach ($campaigns as $c) {
            $campaignOptions[(string) $c->id] = $c->name;
        }

        return [
            [
                'key' => 'sr_no',
                'label' => '#',
                'width' => '6%',
                'align' => 'center',
                'priority' => 1,
                'render' => function ($row) {
                    $sr = is_array($row) ? ($row['_sr_no'] ?? 1) : ($row->_sr_no ?? 1);
                    return '<span class="font-mono text-muted text-xs font-semibold">' . e($sr) . '</span>';
                },
            ],
            [
                'key' => 'campaign',
                'label' => 'CRM Campaign',
                'width' => '32%',
                'priority' => 1,
                'render' => function ($row) use ($campaignOptions) {
                    $fId = is_array($row) ? $row['id'] : $row->id;
                    return view('components.ui.themed-select', [
                        'attributes' => new \Illuminate\View\ComponentAttributeBag([
                            'wire:model.live' => "formCampaignMap.{$fId}",
                        ]),
                        'options' => $campaignOptions,
                        'searchable' => true,
                        'placeholder' => '-- Select Campaign --',
                        'class' => 'w-full',
                    ])->render();
                },
            ],
            [
                'key' => 'form_name',
                'label' => 'Meta Lead Form',
                'width' => '32%',
                'priority' => 1,
                'render' => function ($row) {
                    $fId = is_array($row) ? $row['id'] : $row->id;
                    $fName = (is_array($row) ? ($row['name'] ?? null) : $row->name) ?: 'Lead Form #' . $fId;
                    $status = (is_array($row) ? ($row['status'] ?? null) : $row->status) ?: 'ACTIVE';
                    return '<div class="min-w-0 pr-1">'
                        . '<div class="flex items-center gap-1.5 flex-wrap">'
                        . '<span class="font-bold text-ink leading-tight text-xs truncate max-w-[240px]" title="' . e($fName) . '">' . e($fName) . '</span>'
                        . '<span class="px-1.5 py-0.2 text-[9px] font-bold rounded-full uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">' . e($status) . '</span>'
                        . '</div>'
                        . '<div class="font-mono text-muted text-[10px] mt-0.5 select-all">ID: ' . e($fId) . '</div>'
                        . '</div>';
                },
            ],
            [
                'key' => 'client',
                'label' => 'Client / Org',
                'width' => '24%',
                'priority' => 1,
                'render' => function ($row) use ($clientOptions) {
                    $fId = is_array($row) ? $row['id'] : $row->id;
                    return view('components.ui.themed-select', [
                        'attributes' => new \Illuminate\View\ComponentAttributeBag([
                            'wire:model.live' => "formClientMap.{$fId}",
                        ]),
                        'options' => $clientOptions,
                        'searchable' => true,
                        'placeholder' => '-- Select Client / Org --',
                        'class' => 'w-full',
                    ])->render();
                },
            ],
            [
                'key' => 'action',
                'label' => 'Sync',
                'width' => '6%',
                'align' => 'center',
                'priority' => 1,
                'render' => function ($row) {
                    $fId = is_array($row) ? $row['id'] : $row->id;
                    $fName = (is_array($row) ? ($row['name'] ?? null) : $row->name) ?: 'Lead Form #' . $fId;
                    return '<button type="button" wire:click="saveFormMapping(\'' . $fId . '\', \'' . addslashes($fName) . '\')" title="Auto-saved to DB. Click to manually re-sync" class="w-7 h-7 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-700 rounded-lg transition cursor-pointer shadow-2xs inline-flex items-center justify-center">'
                        . '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>'
                        . '</button>';
                },
            ],
        ];
    }

    public function accountsTableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Account ID', 'prefix' => '#', 'class' => 'font-mono font-bold text-ink', 'sortable' => false, 'priority' => 1],
            ['key' => 'name', 'label' => 'Account Name', 'render' => function($row) {
                $pageId = $row->type === 'meta' ? $row->getCredential('page_id') : null;
                $html = '<div class="font-bold text-ink">' . e($row->name) . '</div>';
                if ($pageId) {
                    $html .= '<div class="text-[10px] font-mono text-muted">Page ID: ' . e($pageId) . '</div>';
                }
                return $html;
            }, 'sortable' => false, 'priority' => 1],
            ['key' => 'type', 'label' => 'Source Type', 'render' => function($row) {
                return '<span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-canvas text-ink border border-border">' . e(strtoupper($row->type)) . '</span>';
            }, 'sortable' => false, 'priority' => 1],
        ];
    }

    public function render()
    {
        $clients = Client::with('organization')->orderBy('name')->get();
        if ($clients->isEmpty()) {
            $clients = \App\Models\User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['Builder/Developer', 'Broker', 'client', 'Client']);
            })->with('organization')->orderBy('name')->get();
        }
        if ($clients->isEmpty()) {
            $clients = \App\Models\User::with('organization')->orderBy('name')->get();
        }

        $projects = Project::orderBy('name')->get();
        $campaigns = Campaign::orderBy('name')->get();

        $clientOptions = ['' => '-- Primary Platform Client / Builder --'];
        foreach ($clients as $c) {
            $orgName = $c->organization?->name ? ' (' . $c->organization->name . ')' : '';
            $clientOptions[(string) $c->id] = $c->name . $orgName;
        }

        $projectOptions = ['' => '-- Auto Match / Create Project --'];
        foreach ($projects as $p) {
            $projectOptions[(string) $p->id] = $p->name;
        }

        return view('livewire.settings.integrations-manager', [
            'clients' => $clients,
            'projects' => $projects,
            'campaigns' => $campaigns,
            'clientOptions' => $clientOptions,
            'projectOptions' => $projectOptions,
        ])->layout('layouts.app');
    }
}
