<?php
namespace App\Services\Concerns;

use App\Models\{Lead, User, Organization, SalesTeam, SalesTeamMember, InternalLeadProcessing, InternalProcessingStatus, AuditLog};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\Rule;

trait ProcessesInternalLeads
{
    public function internalTeams()
    {
        $admins = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'super-admin']));
        return SalesTeam::where(function ($q) use ($admins) {
            $q->where(fn ($owner) => $owner->where('ownable_type', User::class)->whereIn('ownable_id', (clone $admins)->select('id')))
                ->orWhere(fn ($owner) => $owner->where('ownable_type', Organization::class)->whereIn('ownable_id', (clone $admins)->whereNotNull('organization_id')->select('organization_id')));
        });
    }

    public function internalTeamMembers(SalesTeam $team)
    {
        $ownerOrg = $team->ownable_type === Organization::class ? $team->ownable_id : User::find($team->ownable_id)?->organization_id;
        return User::where('status', 'active')->where('organization_id', $ownerOrg)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'super-admin', 'Executive', 'sales-executive', 'Sales Executive', 'sales_executive']))
            ->whereIn('id', SalesTeamMember::where('sales_team_id', $team->id)->where('is_active', true)->select('user_id'));
    }

    public function configureInternalProcessing(Organization $organization, array $settings, User $actor): void
    {
        abort_unless($actor->is_active && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
        Validator::make($settings, ['enabled' => 'required|boolean'])->validate();
        if (! $settings['enabled']) { $organization->update(['internal_processing_enabled' => false]); return; }
        Validator::make($settings, [
            'team_id' => ['required', 'integer', Rule::in($this->internalTeams()->pluck('id')->all())],
            'member_ids' => 'required|array|min:1', 'member_ids.*' => 'integer|distinct',
            'status_codes' => 'required|array|min:1', 'status_codes.*' => ['string', Rule::exists('internal_processing_statuses', 'code')],
            'forward_statuses' => 'required|array|min:1', 'forward_statuses.*' => ['string', Rule::exists('internal_processing_statuses', 'code')->where('can_forward', true)],
            'assignment_method' => ['required', Rule::in(['round_robin', 'capacity', 'manual'])], 'auto_forward' => 'required|boolean',
        ])->validate();
        $team = $this->internalTeams()->findOrFail($settings['team_id']);
        $members = array_map('intval', $settings['member_ids']);
        $eligible = $this->internalTeamMembers($team)->pluck('id')->all();
        if (array_diff($members, $eligible)) throw \Illuminate\Validation\ValidationException::withMessages(['member_ids' => 'Choose active internal executives who belong to the selected platform team.']);
        $statuses = array_values(array_unique(array_merge($settings['status_codes'], InternalProcessingStatus::where('is_system', true)->pluck('code')->all())));
        if (array_diff($settings['forward_statuses'], $statuses)) throw \Illuminate\Validation\ValidationException::withMessages(['forward_statuses' => 'Forwarding statuses must also be available to the internal team.']);
        $organization->update(['internal_processing_enabled' => true, 'internal_processing_team_id' => $team->id,
            'internal_processing_config' => ['member_ids' => $members, 'status_codes' => $statuses, 'forward_statuses' => array_values($settings['forward_statuses']),
                'assignment_method' => $settings['assignment_method'], 'auto_forward' => (bool) $settings['auto_forward']]]);
    }

    public function internalQueueQuery(User $actor)
    {
        $query = InternalLeadProcessing::query();
        if ($actor->is_active && $actor->hasAnyRole(['Super Admin', 'super-admin'])) return $query;
        if (! $actor->is_active || ! $actor->hasAnyRole(['sales-executive', 'Sales Executive', 'Executive'])) return $query->whereRaw('1 = 0');
        $teams = $this->internalTeams()->get()->filter(fn ($team) => $this->internalTeamMembers($team)->whereKey($actor->id)->exists())->pluck('id');
        return $query->where('assigned_user_id', $actor->id)->whereIn('sales_team_id', $teams)
            ->whereIn('sales_team_id', SalesTeamMember::where('user_id', $actor->id)->where('is_active', true)->select('sales_team_id'))
            ->whereHas('organization', fn ($q) => $q->whereJsonContains('internal_processing_config->member_ids', $actor->id));
    }

    public function canProcessInternal(InternalLeadProcessing $record, User $actor): bool
    {
        return $this->internalQueueQuery($actor)->whereKey($record->id)->exists();
    }

    public function hasInternalQueueAccess(User $actor): bool
    {
        if ($actor->is_active && $actor->hasAnyRole(['Super Admin', 'super-admin'])) return true;
        if (! $actor->is_active || ! $actor->hasAnyRole(['sales-executive', 'Sales Executive', 'Executive'])) return false;
        return Organization::whereJsonContains('internal_processing_config->member_ids', $actor->id)->get()->contains(function ($org) use ($actor) {
            $team = $this->internalTeams()->find($org->internal_processing_team_id);
            return $team && $this->internalTeamMembers($team)->whereKey($actor->id)->exists();
        });
    }

    public function startInternalProcessing(Lead $lead): ?InternalLeadProcessing
    {
        if (! $lead->internal_processing_pending) return null;
        return DB::transaction(function () use ($lead) {
            $owner = User::findOrFail($lead->client_id);
            $org = Organization::findOrFail($owner->organization_id);
            $team = $this->internalTeams()->whereKey($org->internal_processing_team_id)->lockForUpdate()->first();
            $record = InternalLeadProcessing::firstOrCreate(['lead_id' => $lead->id], ['organization_id' => $org->id, 'sales_team_id' => $team?->id, 'status_code' => 'received']);
            if (! $record->wasRecentlyCreated) return $record;
            $this->internalEvent($record, 'received', ['after' => $record->getAttributes()], null);
            $config = $org->internal_processing_config ?? [];
            $candidates = $team ? $this->internalTeamMembers($team)->whereIn('id', $config['member_ids'] ?? [])->orderBy('id')->get() : collect();
            $chosen = null;
            if ($candidates->isNotEmpty() && ($config['assignment_method'] ?? 'manual') !== 'manual') {
                if ($config['assignment_method'] === 'capacity') {
                    $chosen = $candidates->sortBy(fn ($user) => InternalLeadProcessing::where('assigned_user_id', $user->id)->whereNull('forwarded_at')->whereNotIn('status_code', InternalProcessingStatus::where('terminal', true)->pluck('code'))->count())->first();
                } else {
                    $ids = $candidates->pluck('id')->all();
                    $last = InternalLeadProcessing::where('sales_team_id', $team->id)->whereNotNull('assigned_user_id')->where('id', '!=', $record->id)->latest('id')->value('assigned_user_id');
                    $index = array_search($last, $ids);
                    $chosen = $candidates->firstWhere('id', $ids[$index === false ? 0 : (($index + 1) % count($ids))]);
                }
            }
            if ($chosen) {
                $record->update(['assigned_user_id' => $chosen->id, 'status_code' => 'calling_pending']);
                $this->internalEvent($record, 'assigned', ['after' => ['assigned_user_id' => $chosen->id]], null);
            }
            $this->internalAlert($record, 'internal_received', 'New lead in internal processing', 'received:'.$record->id);
            if ($chosen) $this->internalAlert($record, 'internal_assigned', 'Lead assigned for internal calling', 'assigned:'.$record->id.':'.$chosen->id);
            return $record;
        });
    }

    public function assignInternalExecutive(int $recordId, int $userId, User $actor): void
    {
        abort_unless($actor->is_active && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
        DB::transaction(function () use ($recordId, $userId, $actor) {
            $record = InternalLeadProcessing::whereKey($recordId)->lockForUpdate()->firstOrFail();
            abort_if($record->handover_requested_at || $record->forwarded_at, 409);
            $org = $record->organization;
            $team = $this->internalTeams()->findOrFail($org->internal_processing_team_id);
            abort_unless(in_array($userId, $org->internal_processing_config['member_ids'] ?? []) && $this->internalTeamMembers($team)->whereKey($userId)->exists(), 422);
            if ($record->assigned_user_id === $userId && $record->sales_team_id === $team->id) return;
            $before = ['assigned_user_id' => $record->assigned_user_id, 'sales_team_id' => $record->sales_team_id];
            $record->update(['assigned_user_id' => $userId, 'sales_team_id' => $team->id, 'status_code' => 'calling_pending']);
            $event = $this->internalEvent($record, 'assigned', ['before' => $before, 'after' => ['assigned_user_id' => $userId, 'sales_team_id' => $team->id]], $actor);
            $this->internalAlert($record, 'internal_assigned', 'Lead assigned for internal calling', 'assignment:'.$event->id);
        });
    }

    public function internalEvent(InternalLeadProcessing $record, string $action, array $details, ?User $actor)
    {
        $event = $record->events()->create(['user_id' => $actor?->id, 'action' => $action, 'details' => $details, 'created_at' => now()]);
        AuditLog::create(['user_id' => $actor?->id, 'action' => 'internal_processing.'.$action, 'organization_id' => $record->organization_id,
            'subject_type' => InternalLeadProcessing::class, 'subject_id' => $record->id, 'from_value' => $details['before'] ?? null, 'to_value' => $details['after'] ?? $details,
            'context' => ['category' => 'internal_processing', 'status' => 'success', 'lead_id' => $record->lead_id, 'event_id' => $event->id], 'created_at' => now()]);
        return $event;
    }

    public function internalAlert(InternalLeadProcessing $record, string $type, string $title, string $key): void
    {
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'super-admin']))->where('status', 'active')->get();
        if ($record->assigned_user_id && ($user = User::find($record->assigned_user_id)) && $this->canProcessInternal($record, $user)) $recipients->push($user);
        foreach ($recipients->unique('id') as $recipient) {
            $category = $type === 'internal_followup_due' ? 'followup' : (in_array($type, ['internal_received', 'internal_assigned']) ? 'assignment' : 'delivery');
            app(\App\Services\NotificationDeliveryService::class)->sendWeb($recipient, $type, $title,
                $record->organization->name.' · '.$record->lead->lead_code, 'internal:'.$key, $category,
                $recipient->hasAnyRole(['Super Admin', 'super-admin']) ? $record->organization_id : $recipient->organization_id, 'internal_processing', $record->id, true);
        }
    }

    public function updateInternalLead(int $recordId, User $actor, array $data): void
    {
        DB::transaction(function () use ($recordId, $actor, $data) {
            $candidate = InternalLeadProcessing::findOrFail($recordId);
            $lead = Lead::withoutGlobalScopes()->findOrFail($candidate->lead_id);
            if ($lead->project_id) \App\Models\Project::withoutGlobalScopes()->whereKey($lead->project_id)->lockForUpdate()->firstOrFail();
            Lead::withoutGlobalScopes()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $record = InternalLeadProcessing::whereKey($recordId)->lockForUpdate()->firstOrFail();
            abort_unless($this->canProcessInternal($record, $actor), 403);
            abort_if($record->handover_requested_at || $record->forwarded_at, 409);
            $config = $record->organization->internal_processing_config ?? [];
            Validator::make($data, ['action' => ['required', Rule::in(['call', 'status', 'remarks', 'followup', 'verify'])], 'remarks' => 'nullable|string|max:10000',
                'status_code' => ['nullable', Rule::in($config['status_codes'] ?? [])], 'calling_status' => ['nullable', Rule::in(['connected', 'not_answered', 'busy', 'failed'])],
                'followup_at' => 'nullable|date|after:now'])->validate();
            $action = $data['action']; $before = $record->getAttributes();
            abort_unless(in_array($action, $this->availableInternalActions($record, $actor)), 422);
            if ($action === 'call') {
                Validator::make($data, ['calling_status' => 'required', 'remarks' => 'required|string|min:3'])->validate();
                $record->call_attempts++;
                $record->verification_status = 'pending'; $record->verified_at = null; $record->verified_by = null;
                if ($record->status_code === 'verified') $record->status_code = 'calling_pending';
                $record->calling_status = $data['calling_status'];
                if ($data['calling_status'] === 'connected') {
                    $record->last_contacted_at = now();
                    if (in_array('contacted', $config['status_codes'] ?? [])) $record->status_code = 'contacted';
                }
                $record->calling_remarks = $data['remarks'];
            } elseif ($action === 'remarks') {
                Validator::make($data, ['remarks' => 'required|string|min:3'])->validate(); $record->calling_remarks = $data['remarks'];
            } elseif ($action === 'followup') {
                Validator::make($data, ['followup_at' => 'required|date|after:now'])->validate();
                abort_unless(in_array('followup_required', $config['status_codes'] ?? []), 422);
                $record->status_code = 'followup_required'; $record->next_followup_at = $data['followup_at'];
            } elseif ($action === 'status') {
                Validator::make($data, ['status_code' => 'required'])->validate();
                $status = InternalProcessingStatus::findOrFail($data['status_code']);
                abort_if($status->is_system, 422);
                $record->status_code = $status->code;
                if (! empty($data['remarks'])) $record->calling_remarks = $data['remarks'];
                if (in_array($status->code, ['contacted', 'interested', 'verified']) && ! $record->last_contacted_at) throw \Illuminate\Validation\ValidationException::withMessages(['status_code' => 'Log a connected call before selecting this status.']);
                if ($status->terminal || ! $status->can_forward) { $record->verification_status = 'pending'; $record->verified_at = null; $record->verified_by = null; $record->next_followup_at = null; }
            }
            if ($action === 'verify' || ($action === 'status' && $record->status_code === 'verified')) {
                if (! $record->last_contacted_at || ! $record->calling_remarks) throw \Illuminate\Validation\ValidationException::withMessages(['verification' => 'Log a connected call and calling remarks before verification.']);
                abort_unless(in_array($record->status_code, $config['forward_statuses'] ?? []) || in_array('verified', $config['status_codes'] ?? []), 422);
                $record->verification_status = 'verified'; $record->verified_at = now(); $record->verified_by = $actor->id;
                if (in_array('verified', $config['status_codes'] ?? []) && (! in_array($record->status_code, $config['forward_statuses'] ?? []) || in_array('verified', $config['forward_statuses'] ?? []))) $record->status_code = 'verified';
            }
            $record->save();
            $event = $this->internalEvent($record, $action, ['before' => $before, 'after' => $record->getAttributes(), 'input' => $data], $actor);
            if ($record->verification_status === 'verified' && ($before['verification_status'] ?? null) !== 'verified') $this->internalAlert($record, 'internal_verified', 'Lead successfully verified', 'verified:'.$event->id);
            if ($record->verification_status === 'verified' && ($config['auto_forward'] ?? false) && in_array($record->status_code, $config['forward_statuses'] ?? [])) $this->requestInternalHandover($record->id, $actor);
        }, 3);
    }

    public function requestInternalHandover(int $recordId, User $actor): InternalLeadProcessing
    {
        $leadId = InternalLeadProcessing::findOrFail($recordId)->lead_id;
        return DB::transaction(function () use ($recordId, $actor, $leadId) {
            // Match the delivery processor's project -> lead lock order.
            $lead = Lead::withoutGlobalScopes()->findOrFail($leadId);
            if ($lead->project_id) \App\Models\Project::withoutGlobalScopes()->whereKey($lead->project_id)->lockForUpdate()->firstOrFail();
            $lead = Lead::withoutGlobalScopes()->whereKey($leadId)->lockForUpdate()->firstOrFail();
            $record = InternalLeadProcessing::whereKey($recordId)->lockForUpdate()->firstOrFail();
            abort_unless($this->canProcessInternal($record, $actor), 403);
            if ($record->handover_requested_at) { if (! $record->forwarded_at) $this->releaseDelivery($lead->id); return $record->fresh(); }
            $config = $record->organization->internal_processing_config ?? [];
            if ($record->verification_status !== 'verified' || ! in_array($record->status_code, $config['forward_statuses'] ?? [])) throw \Illuminate\Validation\ValidationException::withMessages(['handover' => 'Only verified leads in an eligible forwarding status can be handed over.']);
            $record->update(['handover_requested_at' => now(), 'handover_requested_by' => $actor->id, 'next_followup_at' => null]);
            $lead->assigned_to = null; $lead->status = 'new'; $lead->current_stage = 'new';
            $this->scheduleDelivery($lead, true); $lead->save();
            $this->internalEvent($record, 'handover_requested', ['after' => ['delivery_at' => $lead->delivery_at?->toIso8601String()]], $actor);
            $this->releaseDelivery($lead->id);
            return $record->fresh();
        }, 3);
    }

    public function internalStatistics(Organization $org, ?string $from = null, ?string $to = null): array
    {
        $query = InternalLeadProcessing::where('organization_id', $org->id);
        if ($from) $query->where('created_at', '>=', $from);
        if ($to) $query->where('created_at', '<=', $to);
        $received = Lead::withoutGlobalScopes()->whereIn('client_id', User::where('organization_id', $org->id)->select('id'));
        if ($from) $received->where('created_at', '>=', $from);
        if ($to) $received->where('created_at', '<=', $to);
        return ['Total Leads Received' => $received->count(), 'Pending Internal Processing' => (clone $query)->whereNull('handover_requested_at')->whereNull('forwarded_at')->whereNotIn('status_code', InternalProcessingStatus::where('terminal', true)->pluck('code'))->count(),
            'Assigned for Calling' => (clone $query)->whereNotNull('assigned_user_id')->whereNull('last_contacted_at')->whereNull('handover_requested_at')->whereNotIn('status_code', InternalProcessingStatus::where('terminal', true)->pluck('code'))->count(),
            'Contacted Leads' => (clone $query)->whereNotNull('last_contacted_at')->count(), 'Follow-ups Pending' => (clone $query)->whereNotNull('next_followup_at')->whereNull('handover_requested_at')->count(),
            'Verified Leads' => (clone $query)->whereNotNull('verified_at')->count(), 'Forwarded to Client' => (clone $query)->whereNotNull('forwarded_at')->count(),
            'Rejected / Invalid Leads' => (clone $query)->whereIn('status_code', ['not_interested', 'invalid', 'duplicate'])->count()];
    }

    public function availableInternalActions(InternalLeadProcessing $record, User $actor): array
    {
        if (! $this->canProcessInternal($record, $actor) || $record->handover_requested_at || $record->forwarded_at) return [];
        if ($record->status?->terminal) return $actor->hasAnyRole(['Super Admin', 'super-admin']) ? ['status', 'remarks'] : [];
        $config = $record->organization->internal_processing_config ?? [];
        $actions = ['call', 'status', 'remarks'];
        if (in_array('followup_required', $config['status_codes'] ?? [])) $actions[] = 'followup';
        if ($record->last_contacted_at && $record->status?->can_forward && $record->verification_status !== 'verified') $actions[] = 'verify';
        if ($record->verification_status === 'verified' && in_array($record->status_code, $config['forward_statuses'] ?? [])) $actions[] = 'forward';
        return $actions;
    }

    public function internalForwardingFailed(InternalLeadProcessing $record, string $error): void
    {
        if ($record->forwarding_error === $error) return;
        $record->update(['forwarding_error' => $error]);
        $this->internalEvent($record, 'forwarding_failed', ['after' => ['error' => $error]], User::find($record->handover_requested_by));
        $this->internalAlert($record, 'internal_forwarding_failed', 'Client handover failed; review required', 'forward-failed:'.$record->id.':'.now()->toDateString());
    }
}
