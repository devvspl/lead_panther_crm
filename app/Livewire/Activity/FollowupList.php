<?php

namespace App\Livewire\Activity;

use App\Exports\BaseStyledExport;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\User;
use App\Support\LeadPresenter;
use App\Support\NavigationConfig;
use App\Support\TeamAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class FollowupList extends Component
{
    use WithPagination;

    public ?Lead $lead = null;

    // Active Tab: 'all', 'today', 'upcoming', 'overdue', 'completed', 'escalated', 'requests'
    public string $activeTab = 'all';

    // Filters
    public ?int $organizationId = null;
    public ?int $projectId = null;
    public ?int $campaignId = null;
    public ?int $teamId = null;
    public ?int $executiveId = null;
    public string $typeFilter = '';
    public string $statusFilter = '';
    public string $stageFilter = '';
    public string $outcomeFilter = '';
    public string $search = '';
    public string $dateRange = 'all';
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 15;
    public bool $filtersOpen = false;

    // Modal / action state
    public ?int $completingFollowupId = null;
    public string $nextDueAt = '';
    public string $nextNote = '';
    public string $nextFollowupType = 'call';
    public ?string $newStage = null;
    public string $followupOutcome = 'connected';
    public string $followupOutcomeNotes = '';
    public bool $scheduleNextFollowup = false;

    // Reschedule state
    public ?int $reschedulingFollowupId = null;
    public string $rescheduleDueAt = '';
    public string $rescheduleType = 'call';
    public string $rescheduleNote = '';

    // Cancel state
    public ?int $cancellingFollowupId = null;
    public string $cancellationReason = '';

    // Reassign state
    public ?int $reassigningFollowupId = null;
    public ?int $reassigneeId = null;
    public string $reassignDueAt = '';
    public string $reassignNote = '';

    public static function stageOptions(): array
    {
        return [
            'new' => 'New / Received',
            'assigned' => 'Assigned',
            'contacted' => 'Contacted',
            'interested' => 'Interested',
            'follow_up' => 'Follow-up',
            'meeting_scheduled' => 'Meeting Scheduled',
            'site_visit' => 'Site Visit',
            'negotiation' => 'Negotiation',
            'booking' => 'Booking',
            'payment_received' => 'Payment Received',
            'closed_won' => 'Closed Won',
            'lost' => 'Lost',
            'invalid' => 'Invalid / Wrong Number',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'today' => 'Due Today',
            'upcoming' => 'Upcoming',
            'overdue' => 'Overdue',
            'escalated' => 'Escalated',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public function mount(?Lead $lead = null): void
    {
        $this->lead = $lead;
        if (request()->has('tab') && in_array(request()->get('tab'), ['all', 'today', 'upcoming', 'overdue', 'completed', 'escalated', 'requests'])) {
            $this->activeTab = request()->get('tab');
        }
    }

    public function updated($property): void
    {
        if ($property === 'organizationId') {
            $this->projectId = null;
            $this->campaignId = null;
            $this->teamId = null;
            $this->executiveId = null;
        }

        if ($property === 'projectId') {
            $this->campaignId = null;
        }

        if ($property === 'teamId') {
            $this->executiveId = null;
        }

        if (in_array($property, [
            'activeTab', 'organizationId', 'projectId', 'campaignId', 'teamId', 'executiveId',
            'typeFilter', 'statusFilter', 'stageFilter', 'outcomeFilter', 'search',
            'dateRange', 'dateFrom', 'dateTo', 'perPage'
        ])) {
            $this->resetPage();
        }
    }

    public function updatedDateRange(): void
    {
        $now = now();
        $range = match ($this->dateRange) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'lastMonth' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => null,
            default => ['', ''],
        };

        if ($range !== null) {
            $this->dateFrom = $range[0] ? $range[0]->toDateString() : '';
            $this->dateTo = $range[1] ? $range[1]->toDateString() : '';
        }

        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->filtersOpen = ! $this->filtersOpen;
    }

    public function applyFilters(): void
    {
        $this->resetPage();
        $this->dispatch('toast', type: 'info', message: 'Filters applied.');
    }

    public function resetFilters(): void
    {
        $this->reset([
            'organizationId',
            'projectId',
            'campaignId',
            'teamId',
            'executiveId',
            'typeFilter',
            'statusFilter',
            'stageFilter',
            'outcomeFilter',
            'search',
            'dateRange',
            'dateFrom',
            'dateTo',
        ]);
        $this->resetPage();
        $this->dispatch('toast', type: 'info', message: 'Filters reset.');
    }

    public function clearFilter(string $filterName): void
    {
        if ($filterName === 'date') {
            $this->dateRange = 'all';
            $this->dateFrom = '';
            $this->dateTo = '';
        } elseif (property_exists($this, $filterName)) {
            $this->$filterName = is_int($this->$filterName) ? null : '';
        }
        $this->resetPage();
    }

    public function getActiveFilterCountProperty(): int
    {
        $count = 0;
        if (! empty($this->search)) $count++;
        if (! empty($this->organizationId)) $count++;
        if (! empty($this->projectId)) $count++;
        if (! empty($this->campaignId)) $count++;
        if (! empty($this->teamId)) $count++;
        if (! empty($this->executiveId)) $count++;
        if (! empty($this->typeFilter)) $count++;
        if (! empty($this->statusFilter)) $count++;
        if (! empty($this->stageFilter)) $count++;
        if (! empty($this->outcomeFilter)) $count++;
        if ($this->dateRange !== 'all' || ! empty($this->dateFrom) || ! empty($this->dateTo)) $count++;

        return $count;
    }

    public function markDone(int $followupId): void
    {
        $this->completingFollowupId = $followupId;
        $this->nextDueAt = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->nextNote = '';
        $this->followupOutcome = 'connected';
        $this->followupOutcomeNotes = '';
        $this->scheduleNextFollowup = false;
        $this->nextFollowupType = 'call';
    }

    public function completeWithNextStep(): void
    {
        if (! $this->completingFollowupId) {
            return;
        }

        DB::transaction(function () {
            $shouldScheduleNext = $this->scheduleNextFollowup || (! empty($this->nextNote) && ! empty($this->nextDueAt));

            if ($shouldScheduleNext) {
                $this->validate([
                    'nextDueAt' => 'required|date|after:now',
                    'nextNote' => 'nullable|string|max:500',
                    'nextFollowupType' => 'required|string|in:call,whatsapp,meeting,site_visit,other',
                ]);
            }

            $followup = Followup::lockForUpdate()->findOrFail($this->completingFollowupId);
            $lead = ($this->lead && $this->lead->id === $followup->lead_id)
                ? $this->lead
                : ((Lead::usesSalesPanel() ? Lead::panelQuery()->find($followup->lead_id) : null) ?: Lead::withoutGlobalScopes()->find($followup->lead_id));

            if (Lead::usesSalesPanel() && $lead && $lead->assigned_to) {
                Validator::make(['action' => 'completeFollowupAction', 'status' => $followup->status, 'newStage' => $this->newStage], [
                    'action' => Rule::in(array_merge($lead->permittedSalesActions(), ['completeFollowupAction'])),
                    'status' => 'in:pending',
                    'newStage' => 'prohibited',
                ])->validate();

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'lead.followup_completed',
                    'subject_type' => Lead::class,
                    'subject_id' => $lead->id,
                    'organization_id' => $lead->client?->organization_id ?? auth()->user()?->organization_id,
                    'from_value' => json_encode(['followup_id' => $followup->id, 'status' => $followup->status]),
                    'to_value' => json_encode([
                        'status' => 'completed',
                        'outcome' => $this->followupOutcome,
                        'outcome_notes' => $this->followupOutcomeNotes ?: $this->nextNote,
                        'next_due_at' => $shouldScheduleNext ? $this->nextDueAt : null,
                        'remarks' => $this->nextNote,
                    ]),
                    'created_at' => now(),
                ]);
            }

            $followup->update([
                'status' => 'completed',
                'outcome' => $this->followupOutcome ?: 'connected',
                'outcome_notes' => $this->followupOutcomeNotes ?: $this->nextNote,
                'completed_at' => now(),
                'completed_by' => auth()->id(),
            ]);

            if ($followup->isRequested() && $followup->requested_by) {
                $followup->update([
                    'request_status' => Followup::REQUEST_COMPLETED,
                ]);

                $requester = User::find($followup->requested_by);
                if ($requester && $requester->status === 'active') {
                    app(\App\Services\NotificationDeliveryService::class)->sendWeb(
                        $requester,
                        'followup_request_completed',
                        'Follow-Up Request Completed: ' . ($followup->lead?->name ?? 'Lead'),
                        auth()->user()->name . " completed the requested follow-up for " . ($followup->lead?->name ?? 'Lead') . ". Outcome: " . ($followup->outcome_label ?? $followup->outcome),
                        'followup_req_done:' . $followup->id . ':' . now()->timestamp,
                        'followup',
                        $followup->lead?->client?->organization_id ?? $requester->organization_id,
                        'lead',
                        $followup->lead_id,
                        important: true,
                        context: ['lead_id' => $followup->lead_id, 'followup_id' => $followup->id]
                    );
                }
            }

            if ($shouldScheduleNext && $this->nextDueAt) {
                Followup::create([
                    'lead_id' => $followup->lead_id,
                    'created_by' => auth()->id() ?? 1,
                    'due_at' => Carbon::parse($this->nextDueAt),
                    'type' => $this->nextFollowupType ?: 'call',
                    'note' => $this->nextNote ?: 'Follow-up next step',
                    'status' => 'pending',
                ]);
            }

            if ($this->newStage && $this->lead) {
                $this->lead->update(['current_stage' => $this->newStage, 'status' => $this->newStage]);
            }

            $this->completingFollowupId = null;
            $this->scheduleNextFollowup = false;
            $this->dispatch('toast', type: 'success', message: 'Follow-up marked completed.');
        });
    }

    public function openReschedule(int $followupId): void
    {
        $fu = Followup::findOrFail($followupId);
        $this->reschedulingFollowupId = $followupId;
        $this->rescheduleDueAt = $fu->due_at ? $fu->due_at->addDay()->format('Y-m-d\TH:i') : now()->addDay()->format('Y-m-d\TH:i');
        $this->rescheduleType = $fu->type ?: 'call';
        $this->rescheduleNote = $fu->note ?: '';
    }

    public function saveReschedule(): void
    {
        if (! $this->reschedulingFollowupId) {
            return;
        }

        $this->validate([
            'rescheduleDueAt' => 'required|date|after:now',
            'rescheduleType' => 'required|string|in:call,whatsapp,meeting,site_visit,other',
            'rescheduleNote' => 'nullable|string|max:500',
        ]);

        $fu = Followup::lockForUpdate()->findOrFail($this->reschedulingFollowupId);
        if (Lead::usesSalesPanel()) {
            $lead = Lead::panelQuery()->find($fu->lead_id);
            abort_unless($lead, 403);
            Validator::make(['action' => 'rescheduleFollowupAction', 'status' => $fu->status], [
                'action' => Rule::in($lead->permittedSalesActions()),
                'status' => 'in:pending',
            ])->validate();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'lead.followup_rescheduled',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'organization_id' => $lead->client?->organization_id ?? auth()->user()?->organization_id,
                'from_value' => json_encode(['due_at' => $fu->due_at?->toIso8601String()]),
                'to_value' => json_encode(['due_at' => $this->rescheduleDueAt, 'type' => $this->rescheduleType, 'note' => $this->rescheduleNote]),
                'created_at' => now(),
            ]);
        }

        $fu->update([
            'due_at' => Carbon::parse($this->rescheduleDueAt),
            'type' => $this->rescheduleType ?: $fu->type,
            'note' => $this->rescheduleNote ?: $fu->note,
            'escalation_level' => 0,
            'escalated_at' => null,
            'escalation_notes' => null,
        ]);

        $this->reschedulingFollowupId = null;
        $this->dispatch('toast', type: 'success', message: 'Follow-up rescheduled.');
    }

    public function openCancel(int $followupId): void
    {
        $this->cancellingFollowupId = $followupId;
        $this->cancellationReason = '';
    }

    public function saveCancel(): void
    {
        if (! $this->cancellingFollowupId) {
            return;
        }

        $this->validate([
            'cancellationReason' => 'nullable|string|max:500',
        ]);

        $fu = Followup::lockForUpdate()->findOrFail($this->cancellingFollowupId);
        if (Lead::usesSalesPanel()) {
            $lead = Lead::panelQuery()->find($fu->lead_id);
            abort_unless($lead, 403);
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'lead.followup_cancelled',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'organization_id' => $lead->client?->organization_id ?? auth()->user()?->organization_id,
                'from_value' => json_encode(['followup_id' => $fu->id, 'status' => $fu->status]),
                'to_value' => json_encode(['status' => 'cancelled', 'reason' => $this->cancellationReason]),
                'created_at' => now(),
            ]);
        }

        $fu->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $this->cancellationReason ?: 'Cancelled by user',
        ]);

        $this->cancellingFollowupId = null;
        $this->cancellationReason = '';
        $this->dispatch('toast', type: 'success', message: 'Follow-up cancelled.');
    }

    public function openReassign(int $followupId): void
    {
        $fu = Followup::findOrFail($followupId);
        $this->reassigningFollowupId = $fu->id;
        $this->reassigneeId = $fu->assigned_to;
        $this->reassignDueAt = $fu->due_at ? $fu->due_at->format('Y-m-d\TH:i') : now()->addDay()->format('Y-m-d\TH:i');
        $this->reassignNote = $fu->note ?: '';
    }

    public function saveReassign(): void
    {
        if (! $this->reassigningFollowupId) {
            return;
        }

        $fu = Followup::lockForUpdate()->findOrFail($this->reassigningFollowupId);
        $user = auth()->user();

        $this->validate([
            'reassigneeId' => 'required|exists:users,id',
            'reassignDueAt' => 'nullable|date',
            'reassignNote' => 'nullable|string|max:1000',
        ]);

        $newAssignee = User::findOrFail($this->reassigneeId);
        $oldAssignee = $fu->assigned_to ? User::find($fu->assigned_to) : null;

        $fu->update([
            'assigned_to' => $newAssignee->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'due_at' => $this->reassignDueAt ? Carbon::parse($this->reassignDueAt) : $fu->due_at,
            'note' => $this->reassignNote ?: $fu->note,
            'escalation_level' => 0,
            'escalated_at' => null,
            'escalation_notes' => null,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'organization_id' => $fu->lead?->client?->organization_id ?? $user->organization_id,
            'action' => 'lead.followup_reassigned',
            'subject_type' => Lead::class,
            'subject_id' => $fu->lead_id,
            'from_value' => json_encode(['assigned_to' => $oldAssignee?->name]),
            'to_value' => json_encode(['assigned_to' => $newAssignee->name, 'due_at' => $fu->due_at?->toIso8601String()]),
            'created_at' => now(),
        ]);

        app(\App\Services\NotificationDeliveryService::class)->sendWeb(
            $newAssignee,
            'followup_assigned',
            'Follow-Up Reassigned: ' . ($fu->lead?->name ?? 'Lead'),
            $user->name . ' reassigned a follow-up to you.',
            'followup_reassigned:' . $fu->id . ':' . now()->timestamp,
            'followup',
            $fu->lead?->client?->organization_id ?? $user->organization_id,
            'lead',
            $fu->lead_id,
            important: true,
            context: ['lead_id' => $fu->lead_id, 'followup_id' => $fu->id]
        );

        $this->reassigningFollowupId = null;
        $this->dispatch('toast', type: 'success', message: "Follow-up reassigned to {$newAssignee->name}.");
    }

    public function acknowledgeRequest(int $followupId, string $status = 'accepted'): void
    {
        $fu = Followup::findOrFail($followupId);
        $user = auth()->user();

        $fu->update([
            'request_status' => $status,
            'acknowledged_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'organization_id' => $fu->lead?->client?->organization_id ?? $user->organization_id,
            'action' => 'lead.followup_request_' . $status,
            'subject_type' => Lead::class,
            'subject_id' => $fu->lead_id,
            'to_value' => json_encode(['request_status' => $status]),
            'created_at' => now(),
        ]);

        if ($fu->requested_by) {
            $requester = User::find($fu->requested_by);
            if ($requester && $requester->status === 'active') {
                $statusLabel = $status === 'in_progress' ? 'started working on' : 'accepted';
                app(\App\Services\NotificationDeliveryService::class)->sendWeb(
                    $requester,
                    'followup_request_updated',
                    'Follow-Up Request ' . ucfirst($status) . ': ' . ($fu->lead?->name ?? 'Lead'),
                    "{$user->name} has {$statusLabel} your follow-up request.",
                    'followup_req_status:' . $fu->id . ':' . $status . ':' . now()->timestamp,
                    'followup',
                    $fu->lead?->client?->organization_id ?? $requester->organization_id,
                    'lead',
                    $fu->lead_id,
                    important: false,
                    context: ['lead_id' => $fu->lead_id, 'followup_id' => $fu->id]
                );
            }
        }

        $this->dispatch('toast', type: 'success', message: 'Request status updated.');
    }

    protected function getScopedBaseQuery(bool $applyFilters = true)
    {
        $user = auth()->user();
        $roleKey = NavigationConfig::getRoleKey($user);

        $query = Followup::query()->with([
            'lead' => fn ($q) => $q->withoutGlobalScopes()->with([
                'project' => fn ($p) => $p->withoutGlobalScopes(),
                'campaign' => fn ($c) => $c->withoutGlobalScopes(),
                'assignedTo.salesTeamMembers.salesTeam.leader',
                'client.organization',
            ]),
            'creator',
            'completedBy',
            'assignedTo.salesTeamMembers.salesTeam.leader',
            'assignedBy',
            'requestedBy',
        ]);

        if (Lead::usesSalesPanel()) {
            if ($roleKey === 'sales-executive') {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere(function ($sub) use ($user) {
                          $sub->whereNull('assigned_to')
                              ->whereIn('lead_id', Lead::panelQuery()->select('leads.id'));
                      });
                });
            } else {
                $query->whereIn('lead_id', Lead::panelQuery()->select('leads.id'));
            }
        } elseif (! $user->hasAnyRole(['Super Admin', 'super-admin'])) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id)
                  ->orWhereHas('lead', fn ($lq) => $lq->withoutGlobalScopes()->where('assigned_to', $user->id)->orWhere('client_id', $user->id));
            });
        }

        if (! $applyFilters) {
            return $query;
        }

        // Filters
        if ($this->organizationId) {
            $query->whereHas('lead', function ($lq) {
                $lq->withoutGlobalScopes()->where(function ($sub) {
                    $sub->whereHas('client', fn ($c) => $c->where('organization_id', $this->organizationId))
                        ->orWhereIn('client_id', User::where('organization_id', $this->organizationId)->select('id'))
                        ->orWhereIn('assigned_to', User::where('organization_id', $this->organizationId)->select('id'));
                });
            });
        }

        if ($this->projectId) {
            $campaignIds = Campaign::withoutGlobalScopes()->where('project_id', $this->projectId)->pluck('id');
            $query->whereHas('lead', fn ($l) => $l->withoutGlobalScopes()->whereIn('campaign_id', $campaignIds));
        }

        if ($this->campaignId) {
            $query->whereHas('lead', fn ($l) => $l->withoutGlobalScopes()->where('campaign_id', $this->campaignId));
        }

        if ($this->teamId) {
            $team = SalesTeam::find($this->teamId);
            if ($team) {
                $teamUserIds = SalesTeamMember::where('sales_team_id', $this->teamId)->where('is_active', true)->pluck('user_id');
                if ($team->leader_id) {
                    $teamUserIds->push($team->leader_id);
                }
                $teamUserIds = $teamUserIds->filter()->unique()->values();

                $query->where(function ($q) use ($teamUserIds) {
                    $q->whereIn('assigned_to', $teamUserIds)
                      ->orWhere(function ($sub) use ($teamUserIds) {
                          $sub->whereNull('assigned_to')
                              ->whereHas('lead', fn ($l) => $l->withoutGlobalScopes()->whereIn('assigned_to', $teamUserIds));
                      });
                });
            }
        }

        if ($this->executiveId) {
            $query->where(function ($q) {
                $q->where('assigned_to', $this->executiveId)
                  ->orWhere(function ($sub) {
                      $sub->whereNull('assigned_to')
                          ->whereHas('lead', fn ($l) => $l->withoutGlobalScopes()->where('assigned_to', $this->executiveId));
                  });
            });
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->statusFilter) {
            match ($this->statusFilter) {
                'pending' => $query->pending(),
                'completed' => $query->where('status', Followup::STATUS_COMPLETED),
                'cancelled' => $query->where('status', Followup::STATUS_CANCELLED),
                'today' => $query->pending()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]),
                'upcoming' => $query->pending()->where('due_at', '>', now()->endOfDay()),
                'overdue' => $query->pending()->where('due_at', '<', now()),
                'escalated' => $query->where('escalation_level', '>', 0),
                default => null,
            };
        }

        if ($this->stageFilter) {
            $stageValues = Lead::stageValues($this->stageFilter);
            $query->whereHas('lead', fn ($l) => $l->withoutGlobalScopes()->whereIn('current_stage', $stageValues));
        }

        if ($this->outcomeFilter) {
            $query->where('outcome', $this->outcomeFilter);
        }

        if ($this->search) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('note', 'like', $term)
                  ->orWhere('outcome_notes', 'like', $term)
                  ->orWhereHas('lead', function ($l) use ($term) {
                      $l->withoutGlobalScopes()->where(function ($lq) use ($term) {
                          $lq->where('name', 'like', $term)
                             ->orWhere('mobile', 'like', $term)
                             ->orWhere('lead_code', 'like', $term);
                      });
                  });
            });
        }

        if ($this->dateFrom) {
            $query->where('due_at', '>=', Carbon::parse($this->dateFrom)->startOfDay());
        }
        if ($this->dateTo) {
            $query->where('due_at', '<=', Carbon::parse($this->dateTo)->endOfDay());
        }

        return $query;
    }

    public function getTabQuery(bool $applyFilters = true)
    {
        $baseQuery = $this->getScopedBaseQuery($applyFilters);

        return match ($this->activeTab) {
            'today' => (clone $baseQuery)->pending()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->orderBy('due_at', 'asc'),
            'upcoming' => (clone $baseQuery)->pending()->where('due_at', '>', now()->endOfDay())->orderBy('due_at', 'asc'),
            'overdue' => (clone $baseQuery)->pending()->where('due_at', '<', now())->orderBy('due_at', 'asc'),
            'completed' => (clone $baseQuery)->where('status', Followup::STATUS_COMPLETED)->latest('completed_at'),
            'escalated' => (clone $baseQuery)->where('escalation_level', '>', 0)->orderBy('escalation_level', 'desc')->orderBy('due_at', 'asc'),
            'requests' => (clone $baseQuery)->whereNotNull('requested_by')->orderBy('due_at', 'asc'),
            default => (clone $baseQuery)->orderBy('due_at', 'asc'),
        };
    }

    public function exportFiltered()
    {
        $records = $this->getTabQuery(applyFilters: true)->get();

        if ($records->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'No follow-up records found matching the active filters to export.');
            return null;
        }

        $subtitle = 'Export Filtered (' . ucfirst($this->activeTab) . ') | Exported ' . now()->timezone('Asia/Kolkata')->format('d M Y, h:i A');
        $this->dispatch('toast', type: 'success', message: "Export ready — downloading {$records->count()} follow-up records.");

        return $this->generateExcelExport($records, 'filtered', $subtitle);
    }

    public function exportAll()
    {
        $records = $this->getScopedBaseQuery(applyFilters: false)->orderBy('due_at', 'asc')->get();

        if ($records->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'No authorized follow-up records found to export.');
            return null;
        }

        $subtitle = 'Export All Authorized | Exported ' . now()->timezone('Asia/Kolkata')->format('d M Y, h:i A');
        $this->dispatch('toast', type: 'success', message: "Export ready — downloading all {$records->count()} follow-up records.");

        return $this->generateExcelExport($records, 'all', $subtitle);
    }

    public function exportCurrentView()
    {
        $records = $this->getTabQuery(applyFilters: true)->forPage($this->getPage(), $this->perPage)->get();

        if ($records->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'No records on the current page to export.');
            return null;
        }

        $subtitle = 'Export Current Page (' . $this->getPage() . ') | Exported ' . now()->timezone('Asia/Kolkata')->format('d M Y, h:i A');
        $this->dispatch('toast', type: 'success', message: "Export ready — downloading {$records->count()} page records.");

        return $this->generateExcelExport($records, 'current_page', $subtitle);
    }

    public function exportExcel()
    {
        return $this->exportFiltered();
    }

    protected function generateExcelExport($records, string $exportType, string $subtitle)
    {
        $user = auth()->user();
        $shouldMaskPii = LeadPresenter::shouldMaskPii($user);

        $records->loadMissing([
            'lead' => fn ($q) => $q->withoutGlobalScopes()->with([
                'project' => fn ($p) => $p->withoutGlobalScopes(),
                'campaign' => fn ($c) => $c->withoutGlobalScopes(),
                'assignedTo.salesTeamMembers.salesTeam.leader',
                'client.organization',
            ]),
            'creator',
            'completedBy',
            'assignedTo.salesTeamMembers.salesTeam.leader',
            'assignedBy',
            'requestedBy',
        ]);

        $leadIds = $records->pluck('lead_id')->filter()->unique();
        $nextFollowups = Followup::whereIn('lead_id', $leadIds)
            ->where('status', Followup::STATUS_PENDING)
            ->where('due_at', '>', now())
            ->orderBy('due_at', 'asc')
            ->get()
            ->groupBy('lead_id');

        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'followup.exported',
            'subject_type' => Followup::class,
            'organization_id' => $user?->organization_id,
            'to_value' => json_encode([
                'export_type' => $exportType,
                'record_count' => $records->count(),
                'tab' => $this->activeTab,
            ]),
            'created_at' => now(),
        ]);

        $headings = [
            'Follow-Up ID',
            'Lead Code',
            'Customer Name',
            'Mobile Number',
            'Organization / Client',
            'Project',
            'Campaign',
            'Assigned Executive',
            'Team / Team Leader',
            'Follow-Up Action Type',
            'Follow-Up Date & Time',
            'Follow-Up Notes',
            'Follow-Up Outcome',
            'Next Follow-Up Date & Time',
            'Follow-Up Status',
            'Lead Stage',
            'Escalation Status',
            'Created By',
            'Created Date',
            'Completed Date',
        ];

        $columns = [
            'id',
            fn ($fu) => $fu->lead?->lead_code ?: ('LP-' . $fu->lead_id),
            fn ($fu) => $fu->lead?->name ?: 'N/A',
            function ($fu) use ($shouldMaskPii) {
                $mobile = $fu->lead?->mobile ?: ($fu->lead?->phone ?: 'N/A');
                return ($shouldMaskPii && $mobile !== 'N/A') ? LeadPresenter::maskMobile($mobile) : $mobile;
            },
            fn ($fu) => $fu->lead?->client?->organization?->name ?: ($fu->lead?->organization?->name ?: 'N/A'),
            fn ($fu) => $fu->lead?->project?->name ?: 'General',
            fn ($fu) => $fu->lead?->campaign?->name ?: 'General Campaign',
            fn ($fu) => $fu->assignedTo?->name ?: ($fu->lead?->assignedTo?->name ?: 'Unassigned'),
            function ($fu) {
                $assignee = $fu->assignedTo ?: $fu->lead?->assignedTo;
                $teamMember = $assignee?->salesTeamMembers?->first();
                $team = $teamMember?->salesTeam;
                if (! $team) return 'N/A';
                $leaderName = $team->leader?->name;
                return $leaderName ? "{$team->name} ({$leaderName})" : $team->name;
            },
            fn ($fu) => $fu->type_label,
            fn ($fu) => $fu->due_at ? $fu->due_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'N/A',
            fn ($fu) => $fu->note ?: '',
            fn ($fu) => $fu->outcome_label ?: ($fu->outcome ? ucfirst(str_replace('_', ' ', $fu->outcome)) : ($fu->status === 'completed' ? 'Completed' : 'Pending')),
            function ($fu) use ($nextFollowups) {
                $next = $nextFollowups->get($fu->lead_id)?->first();
                return $next?->due_at ? $next->due_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'N/A';
            },
            fn ($fu) => ucfirst($fu->status ?? 'pending'),
            fn ($fu) => $fu->lead?->current_stage ? ucfirst(str_replace(['_', '-'], ' ', $fu->lead->current_stage)) : 'N/A',
            fn ($fu) => $fu->escalation_level > 0 ? ('Level ' . $fu->escalation_level . ($fu->escalation_notes ? ': ' . $fu->escalation_notes : '')) : 'None',
            fn ($fu) => $fu->creator?->name ?: 'System',
            fn ($fu) => $fu->created_at ? $fu->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'N/A',
            fn ($fu) => $fu->completed_at ? $fu->completed_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'N/A',
        ];

        $filename = "lead-followups_{$exportType}_" . now()->format('Y-m-d_Hi') . '.xlsx';

        return Excel::download(
            new BaseStyledExport(
                data: $records,
                title: 'Lead Follow-Ups & Reminders',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['status', 'urgency']
            ),
            $filename
        );
    }

    public function render()
    {
        $user = auth()->user();
        if (TeamAccess::isLeader($user)) {
            abort_unless($user->can('view-team-followups'), 403);
        }

        $baseQuery = $this->getScopedBaseQuery(applyFilters: true);

        // Summary Counts across all tabs (with filters applied)
        $totalCount = (clone $baseQuery)->count();
        $todayCount = (clone $baseQuery)->pending()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count();
        $upcomingCount = (clone $baseQuery)->pending()->where('due_at', '>', now()->endOfDay())->count();
        $overdueCount = (clone $baseQuery)->pending()->where('due_at', '<', now())->count();
        $completedCount = (clone $baseQuery)->where('status', Followup::STATUS_COMPLETED)->count();
        $escalatedCount = (clone $baseQuery)->where('escalation_level', '>', 0)->count();
        $requestsCount = (clone $baseQuery)->whereNotNull('requested_by')->whereIn('request_status', ['pending', 'accepted', 'in_progress'])->count();

        // Tab-specific filtering
        $tabQuery = $this->getTabQuery(applyFilters: true);
        $followups = $tabQuery->paginate($this->perPage);

        // Filter dropdown options
        $isSuperAdmin = $user && $user->hasAnyRole(['Super Admin', 'super-admin']);
        $organizations = $isSuperAdmin ? Organization::where('status', 'active')->orderBy('name')->get() : collect();

        $effectiveOrgId = $isSuperAdmin ? $this->organizationId : ($user?->organization_id);

        $projectQuery = Project::withoutGlobalScopes()->where('status', 'active');
        if ($effectiveOrgId) {
            $projectQuery->where(function ($q) use ($effectiveOrgId, $user) {
                $q->where('client_id', $effectiveOrgId)
                  ->orWhereIn('client_id', User::where('organization_id', $effectiveOrgId)->select('id'))
                  ->orWhereHas('client', fn ($c) => $c->where('organization_id', $effectiveOrgId))
                  ->orWhereIn('id', function ($sub) use ($effectiveOrgId) {
                      $sub->select('campaigns.project_id')
                          ->from('leads')
                          ->join('campaigns', 'leads.campaign_id', '=', 'campaigns.id')
                          ->whereNull('leads.archived_at')
                          ->where(function ($lq) use ($effectiveOrgId) {
                              $lq->whereIn('leads.client_id', User::where('organization_id', $effectiveOrgId)->select('id'))
                                 ->orWhereIn('leads.assigned_to', User::where('organization_id', $effectiveOrgId)->select('id'));
                          });
                  });
                if ($user && $user->id) {
                    $q->orWhere('client_id', $user->id);
                }
            });
        } elseif (! $isSuperAdmin) {
            if ($user?->id) {
                $projectQuery->where('client_id', $user->id);
            } else {
                $projectQuery->whereRaw('1 = 0');
            }
        }
        $projects = $projectQuery->orderBy('name')->get();

        if ($this->projectId && ! $projects->contains('id', (int) $this->projectId)) {
            $this->projectId = null;
        }

        // Campaigns
        $campaignQuery = Campaign::withoutGlobalScopes()->where('status', 'active');
        if ($this->projectId) {
            $campaignQuery->where('project_id', $this->projectId);
        } elseif ($projects->isNotEmpty()) {
            $campaignQuery->whereIn('project_id', $projects->pluck('id'));
        }
        $campaigns = $campaignQuery->orderBy('name')->get();
        if ($this->campaignId && ! $campaigns->contains('id', (int) $this->campaignId)) {
            $this->campaignId = null;
        }

        // Teams
        $teamQuery = SalesTeam::query();
        if ($effectiveOrgId) {
            $teamQuery->where(function ($q) use ($effectiveOrgId) {
                $q->where(fn ($sub) => $sub->where('ownable_type', Organization::class)->where('ownable_id', $effectiveOrgId))
                  ->orWhere(fn ($sub) => $sub->where('ownable_type', User::class)->whereIn('ownable_id', User::where('organization_id', $effectiveOrgId)->select('id')));
            });
        } elseif (! $isSuperAdmin && $user?->organization_id) {
            $orgId = $user->organization_id;
            $teamQuery->where(function ($q) use ($orgId) {
                $q->where(fn ($sub) => $sub->where('ownable_type', Organization::class)->where('ownable_id', $orgId))
                  ->orWhere(fn ($sub) => $sub->where('ownable_type', User::class)->whereIn('ownable_id', User::where('organization_id', $orgId)->select('id')));
            });
        }
        $teams = $teamQuery->with('leader')->orderBy('name')->get();
        if ($this->teamId && ! $teams->contains('id', (int) $this->teamId)) {
            $this->teamId = null;
        }

        // Executives
        $executiveQuery = User::where('status', 'active')
            ->when(! $isSuperAdmin && $user?->organization_id, fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($isSuperAdmin && $this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Executive', 'sales-executive', 'Sales Executive']));

        if ($this->teamId) {
            $teamMemberIds = SalesTeamMember::where('sales_team_id', $this->teamId)->where('is_active', true)->pluck('user_id');
            $executiveQuery->whereIn('id', $teamMemberIds);
        }

        $executives = $executiveQuery->orderBy('name')->get();
        if ($this->executiveId && ! $executives->contains('id', (int) $this->executiveId)) {
            $this->executiveId = null;
        }

        // Backward compatibility for standalone widget if used inside lead drawer
        $myOverdue = (clone $baseQuery)->pending()->where('due_at', '<', now()->startOfDay())->orderBy('due_at', 'asc')->get();
        $myToday = (clone $baseQuery)->pending()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->orderBy('due_at', 'asc')->get();
        $myUpcoming = (clone $baseQuery)->pending()->where('due_at', '>', now()->endOfDay())->orderBy('due_at', 'asc')->get();
        $leadFollowups = ($this->lead && $this->lead->exists)
            ? Followup::where('lead_id', $this->lead->id)->with('lead.project')->latest('due_at')->get()
            : collect();

        $view = view('livewire.activity.followup-list', [
            'followups' => $followups,
            'totalCount' => $totalCount,
            'todayCount' => $todayCount,
            'upcomingCount' => $upcomingCount,
            'overdueCount' => $overdueCount,
            'completedCount' => $completedCount,
            'escalatedCount' => $escalatedCount,
            'requestsCount' => $requestsCount,
            'organizations' => $organizations,
            'projects' => $projects,
            'campaigns' => $campaigns,
            'teams' => $teams,
            'executives' => $executives,
            'stageOptions' => static::stageOptions(),
            'statusOptions' => static::statusOptions(),
            'typeOptions' => Followup::typeLabels(),
            'isSuperAdmin' => $isSuperAdmin,
            'myOverdue' => $myOverdue,
            'myToday' => $myToday,
            'myUpcoming' => $myUpcoming,
            'leadFollowups' => $leadFollowups,
        ]);

        return ($this->lead && $this->lead->exists) ? $view : $view->layout('layouts.app');
    }
}
