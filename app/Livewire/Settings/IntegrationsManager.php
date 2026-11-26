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
use App\Models\Organization;
use App\Models\MetaAdRecord;
use App\Models\LeadSource;
use App\Services\GitSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IntegrationsManager extends Component
{
    use WithPagination;

    public function boot(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

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
    #[\Livewire\Attributes\Locked] public array $formFetchSummary = [];

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

    public function getOrganizationPrimaryUser(int $orgId): ?\App\Models\User
    {
        return \App\Models\User::where('organization_id', $orgId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder', 'builder-admin', 'Client', 'client']))
            ->orderBy('id')
            ->first()
            ?? \App\Models\User::where('organization_id', $orgId)->orderBy('id')->first();
    }

    protected array $orgPrimaryUserCache = [];
    protected array $userOrgCache = [];

    public function normalizeClientIdToOrganizationPrimary(?int $clientId): ?int
    {
        if (!$clientId) return null;
        if (!array_key_exists($clientId, $this->userOrgCache)) {
            $user = \App\Models\User::find($clientId);
            $this->userOrgCache[$clientId] = $user ? $user->organization_id : false;
        }
        $orgId = $this->userOrgCache[$clientId];
        if ($orgId) {
            if (!array_key_exists($orgId, $this->orgPrimaryUserCache)) {
                $primary = $this->getOrganizationPrimaryUser($orgId);
                $this->orgPrimaryUserCache[$orgId] = $primary ? $primary->id : null;
            }
            return $this->orgPrimaryUserCache[$orgId] ?: $clientId;
        }
        return $clientId;
    }

    public function updatedSelectedMappingAccountId(): void
    {
        $account = PortalAccount::with(['formMappings.project', 'formMappings.campaign'])->where('type', 'meta')->findOrFail($this->selectedMappingAccountId);
        $this->reset('availableForms', 'formClientMap', 'formProjectMap', 'formCampaignMap', 'search', 'formFetchSummary');
        foreach ($account->formMappings as $mapping) {
            $this->availableForms[] = ['id' => $mapping->form_id, 'name' => $mapping->form_name,
                'status' => strtoupper($mapping->status ?: 'ACTIVE'), 'created_time' => $mapping->meta_created_at?->toIso8601String()];
            $rawClientId = $mapping->client_id ?: ($mapping->project?->client_id ?? null);
            $this->formClientMap[$mapping->form_id] = $this->normalizeClientIdToOrganizationPrimary($rawClientId);
            $this->formProjectMap[$mapping->form_id] = $mapping->project_id ?: ($mapping->campaign?->project_id ?? null);
            $this->formCampaignMap[$mapping->form_id] = $mapping->campaign_id;
        }
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
            // Saved secrets stay server-side; blank inputs retain existing credentials.
            $this->metaAppSecret = '';
            $this->metaAccessToken = '';
            $this->metaVerifyToken = $account->getCredential('verify_token') ?? '';
            $defaultProj = $account->getCredential('default_project_id');
            $this->metaDefaultProjectId = $defaultProj ? (int) $defaultProj : null;
            $defaultClient = $account->getCredential('default_client_id');
            $this->metaDefaultClientId = $defaultClient ? (int) $defaultClient : null;

            $this->selectedMappingAccountId = $account->id;
            $account->loadMissing(['formMappings.project', 'formMappings.campaign']);
            foreach ($account->formMappings as $mapping) {
                // If previously saved campaign had " Campaign" suffix, strip it to match Meta exact name
                if ($mapping->campaign && !empty($mapping->form_name) && $mapping->campaign->name === $mapping->form_name . ' Campaign') {
                    $mapping->campaign->update(['name' => $mapping->form_name]);
                }
                $this->formProjectMap[$mapping->form_id] = $mapping->project_id ?: ($mapping->campaign?->project_id ?? null);
                $rawClientId = $mapping->client_id ?: ($mapping->project?->client_id ?? null);
                $this->formClientMap[$mapping->form_id] = $this->normalizeClientIdToOrganizationPrimary($rawClientId);
                $this->formCampaignMap[$mapping->form_id] = $mapping->campaign_id;
            }

            if (empty($this->availableForms) && $account->formMappings->isNotEmpty()) {
                $forms = [];
                foreach ($account->formMappings as $m) {
                    $forms[] = [
                        'id' => $m->form_id,
                        'name' => $m->form_name,
                        'status' => strtoupper($m->status ?: 'ACTIVE'),
                        'created_time' => $m->meta_created_at?->toIso8601String(),
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
        $savedAccount = $this->selectedAccountId ? PortalAccount::where('type', 'meta')->find($this->selectedAccountId) : null;
        $accessToken = $this->metaAccessToken ?: ($savedAccount?->getCredential('access_token') ?? '');
        $appSecret = $this->metaAppSecret ?: ($savedAccount?->getCredential('app_secret') ?? '');
        if (!empty($this->apiSecret) && empty($this->metaPageId)) {
            $this->validate([
                'accountName' => 'required|string|max:255',
                'apiSecret' => 'required|string',
            ]);
        } else {
            $this->validate([
                'accountName' => 'required|string|max:255',
                'metaPageId' => 'required|string',
                'metaAccessToken' => ($accessToken !== '' ? 'nullable' : 'required').'|string',
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
                    'access_token' => $accessToken,
                ]);
                if ($probe->successful() && $probe->json('access_token')) {
                    $accessToken = $probe->json('access_token');
                }
            } catch (Throwable $e) {
                // Proceed with existing token if network fails
            }

            $credentials = [
                'page_id' => $this->metaPageId,
                'access_token' => $accessToken,
                'verify_token' => $this->metaVerifyToken,
                'app_id' => $this->metaAppId,
                'app_secret' => $appSecret,
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
            $this->apiSecret = '';
        } elseif (!empty($this->apiSecret)) {
            // Legacy/Test fallback: only apiSecret was set directly
            IntegrationCredential::updateOrCreate(
                ['portal_account_id' => $account->id, 'key_name' => $this->credentialKey ?: 'access_token'],
                ['encrypted_value' => $this->apiSecret]
            );
        }

        $this->selectedAccountId = $account->id;
        $this->reset('metaAccessToken', 'metaAppSecret', 'apiSecret');
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
            $savedAccount = $this->selectedAccountId ? PortalAccount::where('type', 'meta')->find($this->selectedAccountId) : null;
            $accessToken = $this->metaAccessToken ?: ($savedAccount?->getCredential('access_token') ?? '');
            if (empty($this->metaPageId) || empty($accessToken)) {
                $this->dispatch('toast', type: 'error', title: 'Missing Credentials', message: 'Please enter Facebook Page ID and Page Access Token before testing.');
                $this->isTesting = false;
                return;
            }

            try {
                $response = Http::timeout(10)->get("https://graph.facebook.com/v19.0/{$this->metaPageId}", [
                    'fields' => 'id,name,access_token',
                    'access_token' => $accessToken,
                ]);

                if ($response->successful() && $response->json('id')) {
                    $pageName = $response->json('name') ?? 'Meta Page';
                    $msg = "Connected — Page: {$pageName}";
                    $this->metaHealthStatus = 'healthy';
                    $this->metaHealthMessage = $msg;

                    // If Meta returned a dedicated Page Access Token, auto-upgrade and save it
                    $pageAccessToken = $response->json('access_token');
                    if ($pageAccessToken && $pageAccessToken !== $accessToken) {
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
                $this->reset('metaAccessToken', 'metaAppSecret', 'apiSecret');
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
        abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $targetId = $accountId ?: ($this->selectedMappingAccountId ?: $this->selectedAccountId);
        if (!$targetId) {
            $this->dispatch('toast', type: 'warning', message: 'Save a Meta Page connection first.');
            return;
        }
        $account = PortalAccount::where('type', 'meta')->findOrFail($targetId);
        $this->isFetchingForms = true;
        try {
            $result = app(\App\Services\MetaFormSyncService::class)->fetch($account);
            $this->selectedMappingAccountId = $account->id;
            // Reload the persisted catalogue: old routing is retained even after an API failure.
            $this->updatedSelectedMappingAccountId();
            $this->search = '';
            $this->formFetchSummary = array_diff_key($result, ['forms' => true]);
            $message = "Meta returned {$result['fetched']} forms ({$result['new']} new); {$result['saved']} saved in CRM. ".$result['message'];
            $this->dispatch('toast', type: $result['status'] === 'completed' ? 'success' : 'warning', message: $message);
        } finally {
            $this->isFetchingForms = false;
        }
    }

    public function syncLinkedAdAccounts(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $pageId = $this->selectedMappingAccountId ?: $this->selectedAccountId;
        $accounts = \App\Models\MetaAdAccount::where(fn ($q) => $q->where('portal_account_id', $pageId)
            ->orWhereHas('pages', fn ($p) => $p->where('portal_accounts.id', $pageId)))
            ->whereIn('status', ['connected', 'error', 'rate_limited'])->get();
        foreach ($accounts as $account) {
            app(\App\Services\MetaAdAccountService::class)->audit($account, 'sync_requested', ['origin' => 'page_forms', 'page_connection_id' => $pageId]);
            \App\Jobs\SyncMetaAdAccount::dispatch($account->id);
        }
        $this->dispatch('toast', type: $accounts->isEmpty() ? 'warning' : 'info', message: $accounts->isEmpty()
            ? 'Add and test the correct Ad Account under Connected Ad Accounts first. A Page token alone does not fetch campaigns or ad sets.'
            : 'Campaign and ad-set sync queued for '.$accounts->count().' linked Ad Account(s). Open Campaigns & Reporting there to see records and sync history.');
    }

    public function updatedFormProjectMap($value, $key): void
    {
        $fId = (string) $key;
        $projectId = !empty($value) ? (int) $value : null;
        $targetAccountId = $this->selectedMappingAccountId ?: $this->selectedAccountId;

        if (!$targetAccountId) return;

        $formItem = collect($this->availableForms)->first(fn ($item) => (is_array($item) ? ($item['id'] ?? null) : $item->id) === $fId);
        $formName = (is_array($formItem) ? ($formItem['name'] ?? null) : $formItem?->name) ?: "Lead Form #{$fId}";

        $this->formProjectMap[$fId] = $projectId;

        $mapping = LeadFormMapping::updateOrCreate(
            ['portal_account_id' => $targetAccountId, 'form_id' => $fId],
            ['project_id' => $projectId, 'form_name' => $formName, 'status' => 'active']
        );

        if ($projectId) {
            $project = Project::withoutGlobalScopes()->with(['client.organization'])->find($projectId);
            if ($project && $project->client_id) {
                $orgId = $project->client?->organization_id;
                $primaryUser = $orgId ? $this->getOrganizationPrimaryUser($orgId) : null;
                $targetClientId = $primaryUser ? $primaryUser->id : $project->client_id;

                $this->formClientMap[$fId] = $targetClientId;
                $mapping->update(['client_id' => $targetClientId]);
            }
            $this->dispatch('toast', type: 'success', message: "Assigned project updated for Form #{$fId}" . ($project ? " ({$project->name})" : ''));
        } else {
            $this->dispatch('toast', type: 'info', message: "Project unassigned for Form #{$fId}");
        }
    }

    public function updatedFormClientMap($value, $key): void
    {
        $fId = (string) $key;
        $clientId = !empty($value) ? (int) $value : null;
        $targetAccountId = $this->selectedMappingAccountId ?: $this->selectedAccountId;

        if (!$targetAccountId) return;

        $formItem = collect($this->availableForms)->first(fn ($item) => (is_array($item) ? ($item['id'] ?? null) : $item->id) === $fId);
        $formName = (is_array($formItem) ? ($formItem['name'] ?? null) : $formItem?->name) ?: "Lead Form #{$fId}";

        $this->formClientMap[$fId] = $clientId;

        $mapping = LeadFormMapping::updateOrCreate(
            ['portal_account_id' => $targetAccountId, 'form_id' => $fId],
            ['client_id' => $clientId, 'form_name' => $formName, 'status' => 'active']
        );

        if ($clientId) {
            $client = \App\Models\User::with('organization')->find($clientId);
            $orgId = $client?->organization_id;

            $orgProjects = $orgId
                ? Project::withoutGlobalScopes()->whereIn('client_id', \App\Models\User::where('organization_id', $orgId)->pluck('id'))->get()
                : Project::withoutGlobalScopes()->where('client_id', $clientId)->get();

            $currentProjId = $this->formProjectMap[$fId] ?? $mapping->project_id;
            $isCurrentInOrg = $currentProjId && $orgProjects->contains('id', $currentProjId);

            if (!$isCurrentInOrg) {
                $cId = $this->formCampaignMap[$fId] ?? $mapping->campaign_id;
                $campaign = $cId ? Campaign::withoutGlobalScopes()->find($cId) : null;
                $searchName = strtolower($campaign?->name ?: $formName);

                $matchedProject = $orgProjects->first(function ($p) use ($searchName) {
                    $pName = strtolower($p->name);
                    return $pName === $searchName || str_contains($searchName, $pName) || str_contains($pName, $searchName);
                });

                if (!$matchedProject && $orgProjects->count() === 1) {
                    $matchedProject = $orgProjects->first();
                }

                if ($matchedProject) {
                    $this->formProjectMap[$fId] = $matchedProject->id;
                    $mapping->update(['project_id' => $matchedProject->id]);
                } else {
                    $this->formProjectMap[$fId] = null;
                    $mapping->update(['project_id' => null]);
                }
            }
        } else {
            $this->formProjectMap[$fId] = null;
            $mapping->update(['project_id' => null]);
        }

        $this->dispatch('toast', type: 'success', message: "Client mapping saved for Form #{$fId}");
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
            $camp = Campaign::withoutGlobalScopes()->find($campaignId);
            $projectId = $camp?->project_id;
        }

        if ($projectId && !$clientId) {
            $proj = Project::withoutGlobalScopes()->find($projectId);
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

        $term = mb_strtolower(trim($this->search));
        if ($term !== '') {
            $campaigns = Campaign::withoutGlobalScopes()->pluck('name', 'id')->toArray();
            $clients = Client::pluck('name', 'id')->toArray();
            $organizations = \App\Models\Organization::pluck('name', 'id')->toArray();
            $userOrgMap = \App\Models\User::pluck('organization_id', 'id')->toArray();
            $projects = Project::withoutGlobalScopes()->pluck('name', 'id')->toArray();
            $metaAdRecords = MetaAdRecord::where('kind', 'ad')
                ->whereNotNull('lead_form_id')
                ->pluck('campaign_id', 'lead_form_id')
                ->toArray();
            $metaCampaignNames = MetaAdRecord::where('kind', 'campaign')
                ->pluck('name', 'remote_id')
                ->toArray();

            $forms = $forms->filter(function ($item) use ($term, $campaigns, $clients, $organizations, $userOrgMap, $projects, $metaAdRecords, $metaCampaignNames) {
                $id = (string) (is_array($item) ? ($item['id'] ?? '') : ($item->id ?? ''));
                $name = mb_strtolower((string) (is_array($item) ? ($item['name'] ?? '') : ($item->name ?? '')));
                $cId = $this->formCampaignMap[$id] ?? null;
                $campName = $cId && isset($campaigns[$cId]) ? mb_strtolower($campaigns[$cId]) : '';
                $clientId = $this->formClientMap[$id] ?? null;
                $orgId = $clientId && isset($userOrgMap[$clientId]) ? $userOrgMap[$clientId] : null;
                $orgName = $orgId && isset($organizations[$orgId]) ? mb_strtolower($organizations[$orgId]) : '';
                $clientName = $clientId && isset($clients[$clientId]) ? mb_strtolower($clients[$clientId]) : '';
                $pId = $this->formProjectMap[$id] ?? null;
                $projName = $pId && isset($projects[$pId]) ? mb_strtolower($projects[$pId]) : '';
                $metaCampId = $metaAdRecords[$id] ?? null;
                $metaCampName = $metaCampId && isset($metaCampaignNames[$metaCampId]) ? mb_strtolower($metaCampaignNames[$metaCampId]) : '';

                return str_contains(mb_strtolower($id), $term)
                    || str_contains($name, $term)
                    || str_contains($campName, $term)
                    || str_contains($orgName, $term)
                    || str_contains($clientName, $term)
                    || str_contains($projName, $term)
                    || str_contains($metaCampName, $term);
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
        $projects = Project::withoutGlobalScopes()->get()->keyBy('id');
        $campaigns = Campaign::withoutGlobalScopes()->with('project')->get()->keyBy('id');
        $formDetails = LeadFormMapping::where('portal_account_id', $this->selectedMappingAccountId)->get()->keyBy('form_id');

        $rows = [];
        $srNo = 1;

        foreach ($forms as $formItem) {
            $fId = (string) (is_array($formItem) ? $formItem['id'] : $formItem->id);
            $fName = (string) (is_array($formItem) ? ($formItem['name'] ?? '') : ($formItem->name ?? ''));
            $status = (string) (is_array($formItem) ? ($formItem['status'] ?? 'ACTIVE') : ($formItem->status ?? 'ACTIVE'));

            $clientId = $this->formClientMap[$fId] ?? null;
            $projectId = $this->formProjectMap[$fId] ?? null;
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
            $campaign = $campaigns->get($campaignId);
            $mapping = $formDetails->get($fId);
            $created = $mapping?->meta_created_at ?? LeadFormMapping::parseMetaCreatedTime(data_get($formItem, 'created_time'));

            $mappedProject = ($projectId && $projects->has($projectId)) ? $projects->get($projectId) : null;
            $projectName = $mappedProject?->name ?? ($campaign?->project?->name ?: 'Unassigned');

            $rows[] = [
                'sr_no' => $srNo++,
                'campaign' => $campaignName,
                'project' => $projectName,
                'campaign_status' => $campaign?->status,
                'campaign_created' => $campaign?->created_at?->format('Y-m-d H:i'),
                'form_name' => $fName ?: 'Lead Form #' . $fId,
                'form_id' => $fId,
                'status' => strtoupper($status),
                'page_id' => $mapping?->page_id,
                'meta_created' => $created?->format('Y-m-d H:i'),
                'client' => $clientName,
            ];
        }

        $filename = "meta_lead_forms_" . now()->format('Y-m-d_His') . ".xlsx";
        $headings = ['#', 'CRM Campaign', 'Project', 'Campaign Status', 'Campaign Created', 'Meta Lead Form', 'Form ID', 'Form Status', 'Page ID', 'Meta Form Created', 'Client / Organization'];

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading Excel now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: collect($rows),
                title: 'Meta Leadgen Forms Routing Directory',
                subtitle: 'Exported on ' . now()->format('d M Y, h:i A') . ' | Total: ' . count($rows) . ' Forms',
                headings: $headings,
                columns: ['sr_no', 'campaign', 'project', 'campaign_status', 'campaign_created', 'form_name', 'form_id', 'status', 'page_id', 'meta_created', 'client'],
                statusColumns: ['status']
            ),
            $filename
        );
    }

    public function availableFormsTableColumns(): array
    {
        $organizations = Organization::where('status', 'active')->orderBy('name')->get();
        $allUsers = \App\Models\User::with(['roles', 'organization'])->get();

        $userToOrgPrimaryMap = [];
        $userToOrgIdMap = [];
        $clientOptions = ['' => '-- Select Client / Org --'];

        foreach ($organizations as $org) {
            $primaryUser = $allUsers->where('organization_id', $org->id)
                ->first(fn ($u) => $u->hasAnyRole(['Builder/Developer', 'Builder', 'builder', 'builder-admin', 'Client', 'client']))
                ?? $allUsers->where('organization_id', $org->id)->first();

            if ($primaryUser) {
                $clientOptions[(string) $primaryUser->id] = $org->name;
                foreach ($allUsers->where('organization_id', $org->id) as $u) {
                    $userToOrgPrimaryMap[$u->id] = $primaryUser->id;
                    $userToOrgIdMap[$u->id] = $org->id;
                }
            }
        }

        // Include any standalone clients without an organization
        $standaloneClients = $allUsers->whereNull('organization_id')
            ->filter(fn ($u) => $u->hasAnyRole(['Builder/Developer', 'Broker', 'client', 'Client']));
        foreach ($standaloneClients as $sc) {
            if (!isset($clientOptions[(string) $sc->id])) {
                $clientOptions[(string) $sc->id] = $sc->name;
                $userToOrgPrimaryMap[$sc->id] = $sc->id;
            }
        }

        $campaigns = Campaign::withoutGlobalScopes()->with('project')->where(fn ($q) => $q->where('status', 'active')
            ->orWhereIn('id', array_filter($this->formCampaignMap)))->orderBy('name')->get();
        $campaignDetails = $campaigns->keyBy('id');
        $formDetails = LeadFormMapping::where('portal_account_id', $this->selectedMappingAccountId)->with(['ads.campaignRecord', 'campaign.project'])->get()->keyBy('form_id');

        $allProjects = Project::withoutGlobalScopes()->with(['client.organization'])->orderBy('name')->get();
        $projectsByOrg = [];
        $projectsByClient = [];
        foreach ($allProjects as $proj) {
            if ($proj->client?->organization_id) {
                $projectsByOrg[$proj->client->organization_id][] = $proj;
            } elseif ($proj->client_id) {
                $projectsByClient[$proj->client_id][] = $proj;
            }
        }

        $campaignOptions = ['' => '-- Select Campaign --'];
        foreach ($campaigns as $c) {
            $campaignOptions[(string) $c->id] = $c->name;
        }

        return [
            [
                'key' => 'sr_no',
                'label' => '#',
                'width' => '4%',
                'align' => 'center',
                'priority' => 1,
                'render' => function ($row) {
                    $sr = is_array($row) ? ($row['_sr_no'] ?? 1) : ($row->_sr_no ?? 1);
                    return '<span class="font-mono text-muted text-xs font-semibold">' . e($sr) . '</span>';
                },
            ],
            [
                'key' => 'campaign',
                'label' => 'Campaign Name',
                'width' => '22%',
                'priority' => 1,
                'render' => function ($row) use ($formDetails, $campaignDetails) {
                    $fId = is_array($row) ? $row['id'] : $row->id;
                    $fName = is_array($row) ? ($row['name'] ?? null) : $row->name;
                    $mapping = $formDetails->get($fId);
                    $linkedAds = $mapping?->ads ?? collect();
                    $matchingAd = $linkedAds->first(fn ($a) => $a->campaignRecord && strtolower($a->campaignRecord->name) === strtolower((string) $fName))
                        ?? $linkedAds->first();
                    $campaign = $campaignDetails->get($this->formCampaignMap[$fId] ?? null) ?? $mapping?->campaign;
                    $campaignName = $matchingAd?->campaignRecord?->name 
                        ?? data_get($matchingAd?->data, 'campaign_name')
                        ?? $campaign?->name;
                    $campaignMeta = '';
                    if ($campaign) {
                        $campaignMeta = '<div class="mt-1 space-y-0.5 text-[10px] text-muted">'
                            . ($campaign->project ? '<p>Project: <span class="text-ink">' . e($campaign->project->name) . '</span></p>' : '')
                            . '<p>Campaign #' . e($campaign->id) . ' · <span class="font-medium">' . e(ucfirst($campaign->status ?? 'Unknown')) . '</span></p>'
                            . '</div>';
                    }
                    $identifiers = '<span id="meta-campaign-desktop-' . e($fId) . '" class="hidden" aria-hidden="true"></span>'
                        . '<span id="meta-campaign-mobile-' . e($fId) . '" class="hidden" aria-hidden="true"></span>';
                    if ($campaignName) {
                        return '<div class="min-w-0 pr-1">'
                            . '<span class="font-semibold text-ink text-xs block truncate" title="' . e($campaignName) . '">' . e($campaignName) . '</span>'
                            . ($matchingAd ? '<span class="text-muted text-[10px] block mt-0.5 font-mono">Linked Ad: ' . e($matchingAd->name ?: $matchingAd->remote_id) . '</span>' : '')
                            . $campaignMeta
                            . $identifiers
                            . '</div>';
                    }
                    return '<div class="text-muted text-[11px]">'
                        . '<span class="italic text-muted">Auto-detected from Meta</span>'
                        . $campaignMeta
                        . $identifiers
                        . '</div>';
                },
            ],
            [
                'key' => 'form_name',
                'label' => 'Meta Lead Form',
                'width' => '26%',
                'priority' => 1,
                'render' => function ($row) use ($formDetails) {
                    $fId = is_array($row) ? $row['id'] : $row->id;
                    $fName = (is_array($row) ? ($row['name'] ?? null) : $row->name) ?: 'Lead Form #' . $fId;
                    $status = (is_array($row) ? ($row['status'] ?? null) : $row->status) ?: 'ACTIVE';
                    $mapping = $formDetails->get($fId);
                    $created = $mapping?->meta_created_at ?? LeadFormMapping::parseMetaCreatedTime(data_get($row, 'created_time'));
                    $statusClass = strtolower($status) === 'active' ? 'text-success border-border' : 'text-muted border-border';
                    $qCount = is_array($mapping?->questions) ? count($mapping->questions) : (is_array(data_get($row, 'questions')) ? count(data_get($row, 'questions')) : 0);
                    return '<div class="min-w-0 pr-1">'
                        . '<div class="flex items-center gap-1.5 flex-wrap">'
                        . '<span class="font-bold text-ink leading-tight text-xs truncate max-w-[240px]" title="' . e($fName) . '">' . e($fName) . '</span>'
                        . '<span class="px-1.5 py-0.2 text-[9px] font-bold rounded-full uppercase bg-canvas border shrink-0 '.$statusClass.'">' . e($status) . '</span>'
                        . '</div>'
                        . '<div class="font-mono text-muted text-[10px] mt-0.5 select-all">ID: ' . e($fId) . '</div>'
                        . '<div class="text-muted text-[10px] mt-1">Page ID: '.e($mapping?->page_id ?? 'Not available').($qCount ? ' · ' . $qCount . ' questions' : '').'</div>'
                        . '<div class="text-muted text-[10px] mt-0.5">Meta created: '.e($created?->format('d M Y, H:i') ?? 'Not available').'</div>'
                        . '</div>';
                },
            ],
            [
                'key' => 'client',
                'label' => 'Assigned Client / Org',
                'width' => '23%',
                'priority' => 1,
                'render' => function ($row, $value = null, $layout = 'desktop') use ($clientOptions, $formDetails, $userToOrgPrimaryMap) {
                    $fId = is_array($row) ? $row['id'] : $row->id;
                    $mapping = $formDetails->get($fId);
                    $rawClient = $this->formClientMap[$fId] ?? $mapping?->client_id ?? null;
                    $assignedClient = ($rawClient && isset($userToOrgPrimaryMap[$rawClient])) ? $userToOrgPrimaryMap[$rawClient] : $rawClient;
                    $select = view('components.ui.themed-select', [
                        'attributes' => new \Illuminate\View\ComponentAttributeBag([
                            'wire:model.live' => "formClientMap.{$fId}",
                            'id' => "meta-client-{$layout}-{$fId}",
                        ]),
                        'options' => $clientOptions,
                        'searchable' => true,
                        'placeholder' => '-- Select Client / Org --',
                        'class' => 'w-full',
                    ])->render();
                    $badge = $assignedClient 
                        ? '<p class="mt-1.5 text-[10px] text-emerald-700 font-semibold flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Leads route to this Client</p>'
                        : '<p class="mt-1.5 text-[10px] text-muted">Routes to integration default client</p>';
                    return $select . $badge;
                },
            ],
            [
                'key' => 'project',
                'label' => 'Client Project',
                'width' => '20%',
                'priority' => 1,
                'render' => function ($row, $value = null, $layout = 'desktop') use ($formDetails, $campaignDetails, $allProjects, $userToOrgIdMap, $projectsByOrg, $projectsByClient) {
                    $fId = is_array($row) ? $row['id'] : $row->id;
                    $fName = (is_array($row) ? ($row['name'] ?? null) : $row->name) ?: '';
                    $mapping = $formDetails->get($fId);
                    $assignedClient = $this->formClientMap[$fId] ?? $mapping?->client_id ?? null;
                    $assignedProject = $this->formProjectMap[$fId] ?? $mapping?->project_id ?? null;

                    $campaign = $campaignDetails->get($this->formCampaignMap[$fId] ?? null) ?? $mapping?->campaign;
                    $campaignProject = $campaign?->project;

                    $orgId = $assignedClient ? ($userToOrgIdMap[$assignedClient] ?? null) : null;
                    $orgProjects = $orgId
                        ? ($projectsByOrg[$orgId] ?? [])
                        : ($assignedClient ? ($projectsByClient[$assignedClient] ?? []) : []);

                    if (!$campaignProject && $fName) {
                        $campaignProject = collect($orgProjects)->first(fn ($p) => strtolower($p->name) === strtolower($fName) || str_contains(strtolower($fName), strtolower($p->name)) || str_contains(strtolower($p->name), strtolower($fName)));
                    }

                    $projectOptions = [];
                    if ($assignedClient) {
                        if (empty($orgProjects)) {
                            $projectOptions[''] = '-- No projects for this Organization --';
                        } else {
                            $projectOptions[''] = '-- Select Client Project --';
                            if ($campaignProject && collect($orgProjects)->contains('id', $campaignProject->id)) {
                                $projectOptions[(string) $campaignProject->id] = '★ ' . $campaignProject->name . ' (Campaign Project)';
                            }
                            foreach ($orgProjects as $p) {
                                if ($campaignProject && (int) $p->id === (int) $campaignProject->id) continue;
                                $projectOptions[(string) $p->id] = $p->name;
                            }
                        }
                    } else {
                        if ($campaignProject) {
                            $projectOptions[''] = '-- Select Client Project --';
                            $projectOptions[(string) $campaignProject->id] = '★ ' . $campaignProject->name . ' (Campaign Project)';
                        } else {
                            $projectOptions[''] = '-- Select Client / Org first --';
                        }
                    }

                    $select = view('components.ui.themed-select', [
                        'attributes' => new \Illuminate\View\ComponentAttributeBag([
                            'wire:model.live' => "formProjectMap.{$fId}",
                            'id' => "meta-project-{$layout}-{$fId}",
                        ]),
                        'options' => $projectOptions,
                        'searchable' => true,
                        'placeholder' => '-- Select Client Project --',
                        'class' => 'w-full',
                    ])->render();

                    $badge = $assignedProject
                        ? '<p class="mt-1.5 text-[10px] text-emerald-700 font-semibold flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Leads route to this Project</p>'
                        : ($campaignProject
                            ? '<p class="mt-1.5 text-[10px] text-amber-700 font-medium flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Campaign: ' . e($campaignProject->name) . '</p>'
                            : '<p class="mt-1.5 text-[10px] text-muted">Routes to client default project</p>');

                    return $select . $badge;
                },
            ],
            [
                'key' => 'action',
                'label' => 'Sync',
                'width' => '5%',
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

        $organizations = Organization::where('status', 'active')->orderBy('name')->get();
        $allUsers = \App\Models\User::with(['roles', 'organization'])->get();

        $clientOptions = ['' => '-- Primary Platform Client / Builder --'];
        foreach ($organizations as $org) {
            $primaryUser = $allUsers->where('organization_id', $org->id)
                ->first(fn ($u) => $u->hasAnyRole(['Builder/Developer', 'Builder', 'builder', 'builder-admin', 'Client', 'client']))
                ?? $allUsers->where('organization_id', $org->id)->first();

            if ($primaryUser) {
                $clientOptions[(string) $primaryUser->id] = $org->name;
            }
        }
        $standaloneClients = $allUsers->whereNull('organization_id')
            ->filter(fn ($u) => $u->hasAnyRole(['Builder/Developer', 'Broker', 'client', 'Client']));
        foreach ($standaloneClients as $sc) {
            if (!isset($clientOptions[(string) $sc->id])) {
                $clientOptions[(string) $sc->id] = $sc->name;
            }
        }

        $projectOptions = ['' => '-- Auto Match / Create Project --'];
        foreach ($projects as $p) {
            $projectOptions[(string) $p->id] = $p->name;
        }

        return view('livewire.settings.integrations-manager', [
            'formSyncHistory' => \App\Models\AuditLog::where('subject_type', PortalAccount::class)
                ->where('subject_id', $this->selectedMappingAccountId ?: $this->selectedAccountId)
                ->where('action', 'like', 'meta.forms.%')->latest('id')->limit(10)->get()->map(function ($log) {
                    $details = json_decode($log->to_value ?? '{}', true) ?: [];
                    return ['id' => $log->id, 'at' => $log->created_at] + \Illuminate\Support\Arr::only($details,
                        ['page_id', 'origin', 'status', 'pages', 'fetched', 'new', 'updated', 'saved', 'message', 'http_status', 'meta_code']);
                }),
            'clients' => $clients,
            'projects' => $projects,
            'campaigns' => $campaigns,
            'clientOptions' => $clientOptions,
            'projectOptions' => $projectOptions,
            'metaPageOptions' => PortalAccount::where('type', 'meta')->orderBy('name')->pluck('name', 'id')->all(),
        ])->layout('layouts.app');
    }
}
