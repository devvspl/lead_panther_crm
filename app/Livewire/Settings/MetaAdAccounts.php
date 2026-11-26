<?php

namespace App\Livewire\Settings;

use App\Jobs\SyncMetaAdAccount;
use App\Models\{AuditLog, Campaign, MetaAdAccount, MetaAdRecord, PortalAccount};
use App\Services\MetaAdAccountService;
use App\Support\NavigationConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class MetaAdAccounts extends Component
{
    use WithPagination;

    #[Locked] public int $integrationId;
    #[Locked] public ?int $editingId = null;
    #[Locked] public ?int $viewingId = null;
    public string $name = '';
    public string $adAccountId = '';
    public string $businessId = '';
    public string $accessToken = '';
    public string $tokenExpiry = '';
    public string $search = '';
    public string $kind = 'campaign';
    public string $insightLevel = 'campaign';
    public string $insightDatePreset = 'last_30d';
    public string $statusFilter = '';
    public array $campaignMap = [];
    public string $message = '';
    #[Locked] public ?int $mappingRecordId = null;
    public string $mappingClientId = '';
    public string $mappingProjectId = '';
    public string $mappingCampaignId = '';
    #[Locked] public array $mappingProjectOptions = [];
    #[Locked] public bool $mappingClientValid = false;
    #[Locked] public bool $mappingProjectsFailed = false;

    private function campaignRecord(int $id): MetaAdRecord
    {
        abort_unless($this->viewingId, 404);
        return $this->account($this->viewingId)->records()->where('kind', 'campaign')->findOrFail($id);
    }

    public function openCampaignMapping(int $recordId): void
    {
        $record = $this->campaignRecord($recordId);
        $this->resetValidation();
        $this->mappingRecordId = $record->id;
        $this->mappingProjectId = (string) ($record->project_id ?? $record->crmCampaign?->project_id ?? '');
        $this->mappingClientId = (string) ($record->client_id ?? $record->project?->client_id ?? $record->crmCampaign?->project?->client_id ?? '');
        $this->reloadMappingProjects();
        $this->dispatch('open-modal', 'meta-campaign-mapping');
    }

    public function updatedMappingClientId(): void
    {
        $this->reset('mappingProjectId', 'mappingCampaignId');
        $this->resetValidation(['mappingClientId', 'mappingProjectId']);
        $this->reloadMappingProjects();
    }

    public function reloadMappingProjects(): void
    {
        $this->mappingProjectOptions = [];
        $this->mappingClientValid = false;
        $this->mappingProjectsFailed = false;
        if (!ctype_digit($this->mappingClientId) || (int) $this->mappingClientId < 1) return;
        try {
            $client = \App\Models\User::where('status', 'active')->find($this->mappingClientId);
            if (!$client) return;
            $this->mappingClientValid = true;
            $this->mappingProjectOptions = ($client->organization_id
                ? $client->organization->projects()
                : \App\Models\Project::where('client_id', $client->id))
                ->orderBy('projects.name')->pluck('projects.name', 'projects.id')->all();
            if ($this->mappingProjectId !== '' && !array_key_exists($this->mappingProjectId, $this->mappingProjectOptions)) $this->reset('mappingProjectId', 'mappingCampaignId');
        } catch (\Throwable $e) {
            $this->mappingProjectOptions = [];
            $this->mappingProjectsFailed = true;
            report($e);
        }
    }
    public function updatedMappingProjectId(): void { $this->reset('mappingCampaignId'); }

    public function saveCampaignMapping(): void
    {
        $this->validate([
            'mappingClientId' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'mappingProjectId' => 'required|integer|exists:projects,id',
        ], [
            'mappingClientId.required' => 'Please select a Client / Organization.',
            'mappingProjectId.required' => 'CRM Project is required.',
            'mappingProjectId.exists' => 'The selected project does not exist.',
        ]);
        $record = $this->campaignRecord($this->mappingRecordId);
        app(\App\Services\MetaCampaignLeadService::class)->saveMapping(
            $record,
            (int) $this->mappingClientId,
            (int) $this->mappingProjectId,
            $record->crm_campaign_id
        );
        $this->dispatch('close-modal', 'meta-campaign-mapping');
        $this->message = 'Campaign destination saved. Attributed leads will route to the selected Client / Organization and CRM Project.';
    }

    public function fetchCampaignLeads(int $recordId): void
    {
        $record = $this->campaignRecord($recordId);
        if (!$record->project_id && !$record->crm_campaign_id) { $this->openCampaignMapping($recordId); return; }
        try {
            app(\App\Services\MetaCampaignLeadService::class)->start($record);
            $this->message = 'Lead fetch queued. Only forms resolved from this campaign’s ads will be read. Progress appears below the campaign.';
        } catch (\RuntimeException $e) { $this->message = $e->getMessage(); }
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->status === 'active' && NavigationConfig::getRoleKey(auth()->user()) === 'super-admin', 403);
    }

    public function mount(int $integrationId): void
    {
        $this->integrationId = PortalAccount::where('type', 'meta')->findOrFail($integrationId)->id;
    }

    private function account(int $id): MetaAdAccount
    {
        return MetaAdAccount::where('portal_account_id', $this->integrationId)->findOrFail($id);
    }

    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'adAccountId', 'businessId', 'accessToken', 'tokenExpiry', 'message');
        if ($id) {
            $account = $this->account($id);
            $this->editingId = $account->id;
            $this->name = $account->name;
            $this->adAccountId = $account->ad_account_id;
            $this->businessId = $account->business_id ?? '';
            $this->tokenExpiry = $account->token_expires_at?->isFuture() ? $account->token_expires_at->format('Y-m-d\TH:i') : '';
        }
        // Stored tokens never enter Livewire public state or rendered HTML.
        $this->dispatch('open-modal', 'meta-ad-account-editor');
    }

    public function save(): void
    {
        $this->adAccountId = preg_replace('/^act_/', '', trim($this->adAccountId));
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'adAccountId' => ['required', 'regex:/^\d+$/', 'max:64', Rule::unique('meta_ad_accounts', 'ad_account_id')->ignore($this->editingId)],
            'businessId' => 'nullable|regex:/^\d+$/|max:64',
            'accessToken' => [$this->editingId ? 'nullable' : 'required', 'string', 'max:8192'],
            'tokenExpiry' => 'nullable|date|after:now',
        ]);
        $account = DB::transaction(function () use ($data) {
            $account = $this->editingId ? $this->account($this->editingId) : new MetaAdAccount(['portal_account_id' => $this->integrationId]);
            // Changing identity or credentials always requires a new connection test.
            $changed = !$account->exists || $account->ad_account_id !== $data['adAccountId'] || $this->accessToken !== '';
            if ($account->exists && $account->ad_account_id !== $data['adAccountId']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['adAccountId' => 'Add a separate connection for a different Ad Account.']);
            }
            $account->fill([
                'name' => $data['name'],
                'ad_account_id' => $data['adAccountId'],
                'business_id' => $data['businessId'] ?: null,
                'token_expires_at' => $this->tokenExpiry ?: null,
            ]);
            if ($this->accessToken !== '') $account->access_token = $this->accessToken;
            if ($changed) $account->fill(['status' => 'untested', 'last_error' => null, 'retry_after' => null, 'sync_state' => null]);
            $account->save();
            app(MetaAdAccountService::class)->audit($account, $this->editingId ? 'updated' : 'created');
            return $account;
        });
        $this->accessToken = '';
        $this->dispatch('close-modal', 'meta-ad-account-editor');
        $this->testConnection($account->id);
    }

    public function testConnection(int $id): void
    {
        try {
            $account = $this->account($id);
            app(MetaAdAccountService::class)->test($account);
            $this->message = 'Connection verified. Synchronization queued.';
            SyncMetaAdAccount::dispatch($account->id);
        } catch (\RuntimeException $e) { $this->message = 'Connection test failed. See the account status and Recent Sync & Connection History for the failed stage and error code.'; }
    }

    public function disconnect(int $id): void
    {
        $account = $this->account($id);
        $account->update(['status' => 'disconnected', 'access_token' => null, 'last_error' => null, 'retry_after' => null, 'sync_state' => null]);
        app(MetaAdAccountService::class)->audit($account, 'disconnected');
        $this->message = 'Ad Account disconnected. Imported records and Page mappings are preserved.';
    }

    public function sync(int $id): void
    {
        $account = $this->account($id);
        if (!in_array($account->status, ['connected', 'error', 'rate_limited'])) {
            $this->message = 'Test or reconnect this account first.';
            return;
        }
        SyncMetaAdAccount::dispatch($account->id);
        app(MetaAdAccountService::class)->audit($account, 'sync_requested', ['origin' => 'ad_accounts']);
        $this->message = 'Synchronization queued.';
    }

    public function showRecords(int $id): void
    {
        $account = $this->account($id);
        $this->viewingId = $id;
        $this->reset('search', 'kind', 'campaignMap', 'statusFilter', 'insightLevel', 'insightDatePreset');
        $this->statusFilter = 'ACTIVE';
        $this->campaignMap = $account->records()->where('kind', 'campaign')->pluck('crm_campaign_id', 'id')->all();
        $this->resetPage('metaRecordsPage');
    }

    public function updatedSearch(): void { $this->resetPage('metaRecordsPage'); }
    public function updatedStatusFilter(): void { $this->resetPage('metaRecordsPage'); }
    public function updatedInsightLevel(): void { $this->resetPage('metaRecordsPage'); }
    public function updatedInsightDatePreset(): void { $this->resetPage('metaRecordsPage'); }
    public function closeRecords(): void { $this->viewingId = null; }

    public function updatedKind(): void
    {
        abort_unless(in_array($this->kind, ['campaign', 'adset', 'ad', 'insight']), 422);
        $this->statusFilter = $this->kind === 'campaign' ? 'ACTIVE' : '';
        $this->resetPage('metaRecordsPage');
    }

    public function syncInsightsNow(): void
    {
        if (!$this->viewingId) return;
        $account = $this->account($this->viewingId);
        try {
            $result = app(MetaAdAccountService::class)->syncInsights($account, $this->insightLevel, $this->insightDatePreset);
            $this->message = "Insights ({$this->insightLevel}) synchronized: {$result['saved']} record(s) updated.";
        } catch (\Throwable $e) {
            $this->message = 'Insights sync failed: ' . $e->getMessage();
        }
    }

    public function saveMapping(int $recordId): void
    {
        $record = $this->account($this->viewingId)->records()->where('kind', 'campaign')->findOrFail($recordId);
        $value = $this->campaignMap[$recordId] ?? null;
        if ($value) {
            $campaign = Campaign::with('project')->findOrFail($value);
            app(\App\Services\MetaCampaignLeadService::class)->saveMapping($record, (int) $campaign->project->client_id, (int) $campaign->project_id, $campaign->id);
        }
        $this->message = 'Campaign mapping saved. Existing form routing is unchanged.';
    }

    public function render()
    {
        $account = $this->viewingId ? $this->account($this->viewingId) : null;
        $query = $account?->records()->where('kind', $this->kind);

        if ($query) {
            if ($this->kind === 'insight') {
                $query->where(function ($q) {
                    $q->where('insight_level', $this->insightLevel)
                      ->orWhereNull('insight_level');
                });
            }

            if ($this->statusFilter !== '' && $this->kind !== 'insight') {
                $query->where(function ($q) {
                    $q->where('status', $this->statusFilter)
                      ->orWhere('effective_status', $this->statusFilter);
                });
            }

            if (trim($this->search) !== '') {
                $s = trim($this->search);
                $query->where(function ($q) use ($s) {
                    $q->where('name', 'like', '%'.$s.'%')
                      ->orWhere('remote_id', 'like', '%'.$s.'%')
                      ->orWhere('campaign_id', 'like', '%'.$s.'%')
                      ->orWhere('adset_id', 'like', '%'.$s.'%')
                      ->orWhere('lead_form_id', 'like', '%'.$s.'%');
                });
            }
        }

        $records = $query?->with(['client.organization', 'project.client', 'crmCampaign.project.client'])->orderByDesc('report_date')->orderBy('name')->paginate(15, ['*'], 'metaRecordsPage');

        $projectsList = $this->mappingProjectOptions;
        $projectPlaceholder = !$this->mappingClientValid ? 'Select client / organization first'
            : ($this->mappingProjectsFailed ? 'Projects could not be loaded' : (empty($projectsList) ? 'No projects found for this organization.' : 'Select CRM project'));
        $mappingProjects = ['' => $projectPlaceholder] + $projectsList;

        return view('livewire.settings.meta-ad-accounts', [
            'accounts' => MetaAdAccount::where('portal_account_id', $this->integrationId)->withCount(['records', 'pages', 'campaigns', 'adsets', 'ads', 'insights'])->orderBy('name')->get(),
            'selected' => $account,
            'records' => $records ?: collect(),
            'projectsList' => $projectsList,
            'campaignOptions' => ['' => 'No CRM mapping'] + Campaign::orderBy('name')->pluck('name', 'id')->all(),
            'mappingClients' => ['' => 'Select client / organization'] + \App\Models\User::where('status', 'active')->where(function ($q) {
                $q->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['Builder/Developer', 'Client/Organization Admin', 'Organization Admin', 'Client Admin', 'Super Admin', 'super-admin']))
                    ->orWhereIn('id', \App\Models\Project::select('client_id'));
            })->orderBy('name')->pluck('name', 'id')->all(),
            'mappingProjects' => $mappingProjects,
            'logs' => $account ? AuditLog::where('subject_type', MetaAdAccount::class)->where('subject_id', $account->id)->latest('created_at')->limit(15)->get() : AuditLog::where('subject_type', MetaAdAccount::class)->latest('created_at')->limit(10)->get(),
            'formSyncHistory' => AuditLog::where('subject_type', PortalAccount::class)
                ->where('subject_id', $this->integrationId)
                ->where('action', 'like', 'meta.forms.%')->latest('id')->limit(15)->get()->map(function ($log) {
                    $details = json_decode($log->to_value ?? '{}', true) ?: [];
                    return ['id' => $log->id, 'at' => $log->created_at] + \Illuminate\Support\Arr::only($details,
                        ['page_id', 'origin', 'status', 'pages', 'fetched', 'new', 'updated', 'saved', 'message', 'http_status', 'meta_code']);
                }),
        ]);
    }
}
