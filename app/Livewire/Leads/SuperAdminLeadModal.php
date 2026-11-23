<?php

namespace App\Livewire\Leads;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Models\LeadStatusHistory;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class SuperAdminLeadModal extends Component
{
    public bool $isOpen = false;

    public ?int $leadId = null;

    public string $activeTab = 'master'; // 'master', 'meta', 'metadata', 'history'

    // Form inputs for core lead data
    public string $name = '';
    public string $mobile = '';
    public string $email = '';
    public string $city = '';
    public string $location = '';
    public string $property_type = '';
    public ?float $budget = null;
    public string $requirement = '';
    public ?int $client_id = null;
    public ?int $project_id = null;
    public ?int $campaign_id = null;
    public ?int $lead_source_id = null;
    public ?int $assigned_to = null;
    public string $status = 'active';
    public string $current_stage = 'new';
    public int $lead_score = 0;
    public ?float $lead_cost = null;
    public ?string $booking_status = null;
    public ?string $replacement_status = null;
    public bool $delivery_pending = false;
    public bool $internal_processing_pending = false;
    public bool $archive_assignment_hold = false;

    // Metadata management
    public string $newMetaKey = '';
    public string $newMetaValue = '';

    // Meta Lead Intelligence cache
    public array $metaFormAnswers = [];
    public array $metaContext = [];
    public string $rawJsonPretty = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:150',
            'property_type' => 'nullable|string|max:100',
            'budget' => 'nullable|numeric|min:0',
            'requirement' => 'nullable|string',
            'client_id' => 'nullable|integer|exists:users,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'lead_source_id' => 'nullable|integer|exists:lead_sources,id',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'status' => 'required|string|max:50',
            'current_stage' => 'required|string|max:50',
            'lead_score' => 'required|integer|min:0|max:100',
            'lead_cost' => 'nullable|numeric|min:0',
            'booking_status' => 'nullable|string|max:50',
            'replacement_status' => 'nullable|string|max:50',
            'delivery_pending' => 'boolean',
            'internal_processing_pending' => 'boolean',
            'archive_assignment_hold' => 'boolean',
        ];
    }

    public function mount(): void
    {
        // Initial setup
    }

    protected function authorizeSuperAdmin(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['Super Admin', 'super-admin']), 403, 'Unauthorized. Super Admin access only.');
    }

    #[On('open-super-admin-lead-modal')]
    public function open(int $id): void
    {
        $this->authorizeSuperAdmin();

        $lead = Lead::withoutGlobalScopes()->with([
            'client',
            'campaign.project',
            'leadSource',
            'assignedTo',
            'metadata',
            'statusHistory.changer',
            'assignments.assignedTo',
            'assignments.assignedBy',
        ])->find($id);

        if (! $lead) {
            $this->dispatch('toast', type: 'error', message: 'Lead not found.');
            return;
        }

        $this->leadId = $lead->id;
        $this->name = (string) $lead->name;
        $this->mobile = (string) $lead->mobile;
        $this->email = (string) ($lead->email ?? '');
        $this->city = (string) ($lead->city ?? '');
        $this->location = (string) ($lead->location ?? '');
        $this->property_type = (string) ($lead->property_type ?? '');
        $this->budget = $lead->budget !== null ? (float) $lead->budget : null;
        $this->requirement = (string) ($lead->requirement ?? '');
        $this->client_id = $lead->client_id;
        $this->campaign_id = $lead->campaign_id;
        $this->project_id = $lead->project_id;
        $this->lead_source_id = $lead->lead_source_id;
        $this->assigned_to = $lead->assigned_to;
        $this->status = (string) ($lead->status ?? 'active');
        $this->current_stage = (string) ($lead->current_stage ?? 'new');
        $this->lead_score = (int) ($lead->lead_score ?? 0);
        $this->lead_cost = $lead->lead_cost !== null ? (float) $lead->lead_cost : null;
        $this->booking_status = $lead->booking_status;
        $this->replacement_status = $lead->replacement_status;
        $this->delivery_pending = (bool) $lead->delivery_pending;
        $this->internal_processing_pending = (bool) $lead->internal_processing_pending;
        $this->archive_assignment_hold = (bool) $lead->archive_assignment_hold;

        // Populate Meta form answers and context
        $this->populateMetaDetails($lead);

        $this->newMetaKey = '';
        $this->newMetaValue = '';
        $this->activeTab = 'master';
        $this->isOpen = true;
    }

    public function updated(string $property, mixed $value): void
    {
        if (in_array($property, ['client_id', 'project_id', 'campaign_id', 'lead_source_id', 'assigned_to'])) {
            if ($value === '' || $value === '0' || $value === 0 || $value === 'null') {
                $this->{$property} = null;
            } elseif (is_numeric($value)) {
                $this->{$property} = (int) $value;
            }
        }
    }

    public function updatedProjectId(mixed $newProjectId): void
    {
        $newProjectId = $newProjectId ? (int) $newProjectId : null;
        if ($newProjectId) {
            // If current campaign does not belong to new project, clear or re-link
            if ($this->campaign_id) {
                $camp = Campaign::withoutGlobalScopes()->find($this->campaign_id);
                if (! $camp || $camp->project_id != $newProjectId) {
                    $matching = Campaign::withoutGlobalScopes()->where('project_id', $newProjectId)->first();
                    $this->campaign_id = $matching?->id;
                }
            } else {
                $matching = Campaign::withoutGlobalScopes()->where('project_id', $newProjectId)->first();
                $this->campaign_id = $matching?->id;
            }
        }
    }

    public function updatedCampaignId(mixed $newCampaignId): void
    {
        $newCampaignId = $newCampaignId ? (int) $newCampaignId : null;
        if ($newCampaignId) {
            $camp = Campaign::withoutGlobalScopes()->find($newCampaignId);
            if ($camp && $camp->project_id) {
                $this->project_id = $camp->project_id;
            }
        }
    }

    protected function populateMetaDetails(Lead $lead): void
    {
        $answersMeta = $lead->metadata->firstWhere('key', 'meta_form_answers');
        if ($answersMeta && ! empty($answersMeta->value)) {
            $decoded = is_string($answersMeta->value) ? json_decode($answersMeta->value, true) : $answersMeta->value;
            $this->metaFormAnswers = is_array($decoded) ? $decoded : [];
        } else {
            $this->metaFormAnswers = [];
        }

        $rawJsonMeta = $lead->metadata->firstWhere('key', 'raw_json') ?? $lead->metadata->firstWhere('key', 'meta_raw_lead');
        if ($rawJsonMeta && ! empty($rawJsonMeta->value)) {
            $decodedRaw = is_string($rawJsonMeta->value) ? json_decode($rawJsonMeta->value, true) : $rawJsonMeta->value;
            $this->rawJsonPretty = is_array($decodedRaw)
                ? json_encode($decodedRaw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : (string) $rawJsonMeta->value;
        } else {
            $this->rawJsonPretty = '';
        }

        $this->metaContext = [
            'lead_id' => $lead->getMetaAttribute('meta_lead_id'),
            'form_id' => $lead->getMetaAttribute('meta_form_id'),
            'form_name' => $lead->getMetaAttribute('meta_form_name'),
            'page_id' => $lead->getMetaAttribute('meta_page_id'),
            'ad_id' => $lead->getMetaAttribute('meta_ad_id'),
            'ad_name' => $lead->getMetaAttribute('meta_ad_name'),
            'adset_id' => $lead->getMetaAttribute('meta_adset_id'),
            'campaign_id' => $lead->getMetaAttribute('meta_campaign_id'),
            'campaign_name' => $lead->getMetaAttribute('meta_campaign_name'),
            'utm_source' => $lead->getMetaAttribute('utm_source'),
            'utm_medium' => $lead->getMetaAttribute('utm_medium'),
            'utm_campaign' => $lead->getMetaAttribute('utm_campaign'),
        ];
    }

    public function saveLead(): void
    {
        $this->authorizeSuperAdmin();

        $this->client_id = $this->client_id ? (int) $this->client_id : null;
        $this->project_id = $this->project_id ? (int) $this->project_id : null;
        $this->campaign_id = $this->campaign_id ? (int) $this->campaign_id : null;
        $this->lead_source_id = $this->lead_source_id ? (int) $this->lead_source_id : null;
        $this->assigned_to = $this->assigned_to ? (int) $this->assigned_to : null;

        $this->validate();

        $lead = Lead::withoutGlobalScopes()->with(['client', 'metadata'])->findOrFail($this->leadId);

        $oldValues = [
            'name' => $lead->name,
            'mobile' => $lead->mobile,
            'email' => $lead->email,
            'city' => $lead->city,
            'location' => $lead->location,
            'property_type' => $lead->property_type,
            'budget' => $lead->budget,
            'client_id' => $lead->client_id,
            'project_id' => $lead->project_id,
            'campaign_id' => $lead->campaign_id,
            'lead_source_id' => $lead->lead_source_id,
            'assigned_to' => $lead->assigned_to,
            'status' => $lead->status,
            'current_stage' => $lead->current_stage,
            'lead_score' => $lead->lead_score,
            'lead_cost' => $lead->lead_cost,
            'booking_status' => $lead->booking_status,
            'replacement_status' => $lead->replacement_status,
        ];

        DB::transaction(function () use ($lead, $oldValues) {
            // Resolve campaign if project changed and campaign not assigned
            if ($this->project_id && empty($this->campaign_id)) {
                $campaign = Campaign::withoutGlobalScopes()->where('project_id', $this->project_id)->first();
                if (! $campaign) {
                    $sourceId = $this->lead_source_id ?: (LeadSource::first()?->id ?? 1);
                    $campaign = Campaign::create([
                        'project_id' => $this->project_id,
                        'name' => 'General Campaign',
                        'lead_source_id' => $sourceId,
                        'status' => 'active',
                    ]);
                }
                $this->campaign_id = $campaign->id;
            }

            // Track assignment change
            $assignmentChanged = $lead->assigned_to !== $this->assigned_to;

            $lead->fill([
                'name' => $this->name,
                'mobile' => $this->mobile,
                'email' => $this->email ?: null,
                'city' => $this->city ?: null,
                'location' => $this->location ?: null,
                'property_type' => $this->property_type ?: null,
                'budget' => $this->budget,
                'requirement' => $this->requirement ?: null,
                'client_id' => $this->client_id,
                'campaign_id' => $this->campaign_id,
                'lead_source_id' => $this->lead_source_id,
                'assigned_to' => $this->assigned_to,
                'status' => $this->status,
                'current_stage' => $this->current_stage,
                'lead_score' => $this->lead_score,
                'lead_cost' => $this->lead_cost,
                'booking_status' => $this->booking_status,
                'replacement_status' => $this->replacement_status,
                'delivery_pending' => $this->delivery_pending,
                'internal_processing_pending' => $this->internal_processing_pending,
                'archive_assignment_hold' => $this->archive_assignment_hold,
            ]);

            if ($assignmentChanged) {
                if ($this->assigned_to) {
                    $lead->executive_assigned_at = now();
                    LeadAssignment::create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $this->assigned_to,
                        'assigned_by' => auth()->id(),
                        'assignment_source' => 'super_admin_manual',
                        'assigned_at' => now(),
                    ]);
                } else {
                    $lead->executive_assigned_at = null;
                }
            }

            // If current stage changed, record in status history
            if ($lead->isDirty('current_stage') || $lead->isDirty('status')) {
                LeadStatusHistory::create([
                    'lead_id' => $lead->id,
                    'from_status' => $oldValues['current_stage'] ?? 'new',
                    'to_status' => $this->current_stage,
                    'changed_by' => auth()->id(),
                    'changed_at' => now(),
                ]);
            }

            $lead->save();

            // Record in audit log
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'super_admin_lead_master_update',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'organization_id' => $lead->client?->organization_id,
                'from_value' => json_encode($oldValues),
                'to_value' => json_encode([
                    'name' => $this->name,
                    'mobile' => $this->mobile,
                    'email' => $this->email,
                    'city' => $this->city,
                    'location' => $this->location,
                    'property_type' => $this->property_type,
                    'budget' => $this->budget,
                    'client_id' => $this->client_id,
                    'project_id' => $this->project_id,
                    'campaign_id' => $this->campaign_id,
                    'lead_source_id' => $this->lead_source_id,
                    'assigned_to' => $this->assigned_to,
                    'status' => $this->status,
                    'current_stage' => $this->current_stage,
                    'lead_score' => $this->lead_score,
                ]),
                'created_at' => now(),
            ]);
        });

        $this->dispatch('toast', type: 'success', message: 'Lead data updated successfully by Super Admin.');
        $this->dispatch('lead-updated', id: $lead->id);
        $this->dispatch('refresh-leads');
        $this->close();
    }

    public function unassignExecutive(): void
    {
        $this->authorizeSuperAdmin();
        $this->assigned_to = null;
        $this->dispatch('toast', type: 'info', message: 'Executive unassigned in editor. Click "Save Lead Changes" to persist.');
    }

    public function addMetadataKey(): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'newMetaKey' => 'required|string|max:100',
            'newMetaValue' => 'required|string',
        ]);

        $lead = Lead::withoutGlobalScopes()->findOrFail($this->leadId);

        LeadMetadata::updateOrCreate(
            ['lead_id' => $lead->id, 'key' => trim($this->newMetaKey)],
            ['value' => trim($this->newMetaValue)]
        );

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'super_admin_add_lead_metadata',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'to_value' => json_encode(['key' => $this->newMetaKey, 'value' => $this->newMetaValue]),
            'created_at' => now(),
        ]);

        $this->newMetaKey = '';
        $this->newMetaValue = '';
        $this->dispatch('toast', type: 'success', message: 'Metadata key added/updated.');
    }

    public function deleteMetadataKey(int $metadataId): void
    {
        $this->authorizeSuperAdmin();

        $item = LeadMetadata::where('lead_id', $this->leadId)->find($metadataId);
        if ($item) {
            $key = $item->key;
            $item->delete();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'super_admin_delete_lead_metadata',
                'subject_type' => Lead::class,
                'subject_id' => $this->leadId,
                'from_value' => json_encode(['deleted_key' => $key]),
                'created_at' => now(),
            ]);

            $this->dispatch('toast', type: 'success', message: "Metadata key '{$key}' removed.");
        }
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->leadId = null;
        $this->metaFormAnswers = [];
        $this->metaContext = [];
        $this->rawJsonPretty = '';
    }

    public function render()
    {
        $lead = null;
        $metadataList = collect();
        $historyList = collect();
        $assignmentList = collect();

        if ($this->isOpen && $this->leadId) {
            $lead = Lead::withoutGlobalScopes()->with([
                'client.organization',
                'campaign.project',
                'leadSource',
                'assignedTo',
                'metadata',
                'statusHistory.changer',
                'assignments.assignedTo',
                'assignments.assignedBy',
            ])->find($this->leadId);

            if ($lead) {
                $metadataList = $lead->metadata()->orderBy('key')->get();
                $historyList = $lead->statusHistory()->with('user')->latest()->limit(25)->get();
                $assignmentList = $lead->assignments()->with(['assignedTo', 'assignedBy'])->latest()->limit(20)->get();
            }
        }

        $clients = User::where(function ($q) {
            $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['builder', 'client', 'Builder/Developer']))
              ->orWhereNotNull('organization_id');
        })->orWhere('id', $this->client_id)->orderBy('name')->get(['id', 'name', 'email']);

        $projects = Project::orderBy('name')->get(['id', 'name']);

        $campaignsQuery = Campaign::withoutGlobalScopes();
        if ($this->project_id) {
            $campaignsQuery->where('project_id', $this->project_id);
        }
        $campaigns = $campaignsQuery->orderBy('name')->get(['id', 'name', 'project_id']);

        $leadSources = LeadSource::orderBy('name')->get(['id', 'name']);

        $executives = User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $stages = [
            'new' => 'New / Received',
            'assigned' => 'Assigned',
            'contacted' => 'Contacted / Qualified',
            'interested' => 'Interested',
            'follow_up' => 'Follow Up',
            'meeting_scheduled' => 'Meeting Scheduled',
            'site_visit' => 'Site Visit',
            'negotiation' => 'Negotiation',
            'booking' => 'Booking Done',
            'payment_received' => 'Payment Received',
            'closed_won' => 'Closed Won',
            'lost' => 'Lost / Dropped',
            'invalid' => 'Invalid / Fake',
        ];

        $statuses = [
            'active' => 'Active',
            'converted' => 'Converted',
            'lost' => 'Lost',
            'invalid' => 'Invalid',
            'replaced' => 'Replaced',
            'archived' => 'Archived',
        ];

        return view('livewire.leads.super-admin-lead-modal', [
            'lead' => $lead,
            'metadataList' => $metadataList,
            'historyList' => $historyList,
            'assignmentList' => $assignmentList,
            'clients' => $clients,
            'projects' => $projects,
            'campaigns' => $campaigns,
            'leadSources' => $leadSources,
            'executives' => $executives,
            'stages' => $stages,
            'statuses' => $statuses,
        ]);
    }
}
