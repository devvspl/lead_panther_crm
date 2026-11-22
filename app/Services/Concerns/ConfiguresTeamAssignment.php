<?php
namespace App\Services\Concerns;

use App\Models\{Organization, User, Lead, LeadAssignment, SalesTeam, SalesTeamMember, AuditLog};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ConfiguresTeamAssignment
{
    public function assignmentDefaults(Organization $org): array
    {
        $teams = $this->assignmentTeams($org->id)->pluck('id');
        $mode = $org->getLeadAssignmentMode();
        $isAuto = ($mode === Organization::ASSIGNMENT_MODE_AUTOMATIC);
        $method = in_array($org->distribution_method,['round_robin','priority','manual']) ? $org->distribution_method : ($org->distribution_method === 'capacity' ? 'equal_distribution' : 'round_robin');
        return array_replace([
            'enabled' => $isAuto,
            'assignment_mode' => $mode,
            'method' => $method,
            'daily_limit' => 0,
            'batch_size' => 1,
            'team_id' => $teams->count() === 1 ? $teams->first() : null,
            'timezone' => $org->delivery_timezone ?: config('app.timezone'),
            'working_days' => [0,1,2,3,4,5,6],
            'start_time' => '00:00',
            'end_time' => '23:59',
            'project_ids' => [],
            'campaign_ids' => [],
            'members' => [],
        ], $org->assignment_config ?? []);
    }

    public function assignmentExecutives(int $orgId)
    {
        $teams = $this->assignmentTeams($orgId)->select('id');
        return User::where('organization_id', $orgId)->whereHas('roles', fn ($q) => $q->whereIn('name', ['Executive','Sales Executive','sales-executive','sales_executive']))
            ->whereIn('id', SalesTeamMember::whereIn('sales_team_id', $teams)->select('user_id'))->orderBy('id');
    }

    public function assignmentTeams(int $orgId)
    {
        return SalesTeam::where(fn ($q) => $q->where(fn ($q) => $q->where('ownable_type',Organization::class)->where('ownable_id',$orgId))
            ->orWhere(fn ($q) => $q->where('ownable_type',User::class)->whereIn('ownable_id',User::where('organization_id',$orgId)->select('id'))));
    }

    public function saveAssignmentConfiguration(Organization $org, array $input, User $actor): void
    {
        $leader = \App\Support\TeamAccess::isLeader($actor);
        $isSuperAdmin = $actor->hasRole('Super Admin') || $actor->hasRole('super-admin');
        $current = $this->assignmentDefaults($org);
        $leaderIds = [];
        if ($leader && !$isSuperAdmin) {
            abort_unless($actor->is_active && $actor->organization_id === $org->id && $actor->can('assign-team-leads')
                && $current['team_id'] && \App\Support\TeamAccess::teams($actor)->whereKey($current['team_id'])->exists(), 403);
            $leaderIds = \App\Support\TeamAccess::executives($actor, false)->whereIn('id', SalesTeamMember::where('sales_team_id', $current['team_id'])->select('user_id'))->pluck('id')->all();
            if (array_diff(array_keys($input['members'] ?? []), $leaderIds)) throw ValidationException::withMessages(['settings.members'=>'Only your assignment team members can be configured.']);
            $allowed = array_intersect_key($input, array_flip(['enabled','assignment_mode','method','daily_limit','batch_size','working_days','start_time','end_time']));
            foreach ($input['members'] ?? [] as $id=>$member) $current['members'][$id]['available'] = $member['available'] ?? true;
            $input = array_replace($current, $allowed);
            foreach ($input['members'] as &$member) $member['priority'] ??= 1;
            unset($member);
            if (!in_array($input['method'], ['round_robin','equal_distribution','manual']) && !($input['method'] === 'priority' && $current['method'] === 'priority')) throw ValidationException::withMessages(['settings.method'=>'Choose Round Robin, Equal Distribution or Manual Assignment.']);
        } else {
            abort_unless($actor->is_active && $isSuperAdmin, 403);
        }

        // Determine effective assignment mode
        $mode = $input['assignment_mode'] ?? null;
        $enabled = isset($input['enabled']) ? (bool) $input['enabled'] : null;

        if ($mode === Organization::ASSIGNMENT_MODE_MANUAL && $enabled !== true) {
            $input['assignment_mode'] = Organization::ASSIGNMENT_MODE_MANUAL;
            $input['enabled'] = false;
        } elseif ($enabled === false) {
            $input['assignment_mode'] = Organization::ASSIGNMENT_MODE_MANUAL;
            $input['enabled'] = false;
        } else {
            $input['assignment_mode'] = Organization::ASSIGNMENT_MODE_AUTOMATIC;
            $input['enabled'] = true;
        }

        $data = validator($input, [
            'enabled'=>'required|boolean',
            'assignment_mode'=>'required|in:automatic,manual',
            'method'=>'required|in:round_robin,equal_distribution,priority,manual',
            'team_id'=>'nullable|integer',
            'daily_limit'=>'required|integer|min:0', 'batch_size'=>'required|integer|min:1|max:1000', 'timezone'=>'required|timezone',
            'working_days'=>'required|array|min:1', 'working_days.*'=>'integer|between:0,6',
            'start_time'=>'required|date_format:H:i', 'end_time'=>'required|date_format:H:i|after_or_equal:start_time',
            'project_ids'=>'array', 'project_ids.*'=>'integer', 'campaign_ids'=>'array', 'campaign_ids.*'=>'integer',
            'members'=>'array', 'members.*.available'=>'required|boolean', 'members.*.priority'=>'required|integer|min:1',
        ])->validate();
        $projectIds = $org->projects()->withoutGlobalScopes()->pluck('projects.id')->all();
        $campaignIds = \App\Models\Campaign::withoutGlobalScopes()->whereIn('project_id', $projectIds)->pluck('id')->all();
        if ((!empty($data['team_id']) && !$this->assignmentTeams($org->id)->whereKey($data['team_id'])->exists()) || array_diff($data['project_ids'], $projectIds) || array_diff($data['campaign_ids'], $campaignIds)
            || array_diff(array_keys($data['members']), $this->assignmentExecutives($org->id)->pluck('id')->all())) {
            throw ValidationException::withMessages(['assignment' => 'Select projects, campaigns and executives belonging to this organization.']);
        }
        DB::transaction(function () use ($org, $data, $actor, $leader, $leaderIds, $isSuperAdmin) {
            $org = Organization::whereKey($org->id)->lockForUpdate()->firstOrFail();
            if ($leader && !$isSuperAdmin) {
                $latest = $this->assignmentDefaults($org);
                abort_unless($latest['team_id'] === $data['team_id'] && \App\Support\TeamAccess::teams($actor)->whereKey($data['team_id'])->exists(),403);
                foreach (['timezone','project_ids','campaign_ids'] as $key) $data[$key] = $latest[$key];
                $members = $latest['members'];
                foreach ($leaderIds as $id) {
                    if (isset($data['members'][$id])) $members[$id] = array_replace($members[$id] ?? ['priority'=>1], ['available'=>$data['members'][$id]['available']]);
                }
                $data['members'] = $members;
            }
            $before = $org->assignment_config;
            $org->update([
                'lead_assignment_mode' => $data['assignment_mode'],
                'assignment_config' => $data,
                'auto_assign_enabled' => $data['enabled'],
                'distribution_method' => $data['method'],
            ]);
            AuditLog::create(['organization_id'=>$org->id, 'user_id'=>$actor->id, 'action'=>'assignment.configuration_updated',
                'subject_type'=>Organization::class, 'subject_id'=>$org->id, 'from_value'=>$before, 'to_value'=>$data, 'created_at'=>now()]);
        });
    }

    private function assignmentDay(array $settings): array
    {
        $start = now()->setTimezone($settings['timezone'])->startOfDay();
        return [$start->copy()->setTimezone(config('app.timezone')), $start->addDay()->setTimezone(config('app.timezone'))];
    }

    public function assignmentStatistics(Organization $org, ?array $userIds = null): array
    {
        $settings = $this->assignmentDefaults($org);
        [$start,$end] = $this->assignmentDay($settings);
        $pending = $this->assignmentPendingLeads($org->id)->count();
        return $this->assignmentExecutives($org->id)->when($userIds !== null, fn ($q) => $q->whereIn('id',$userIds))->get()->map(function ($user) use ($settings,$start,$end,$pending) {
            $today = LeadAssignment::where('assigned_to',$user->id)->where('assigned_at','>=',$start)->where('assigned_at','<',$end)->count();
            return ['id'=>$user->id,'name'=>$user->name, 'team'=>$this->assignmentTeams($user->organization_id)->whereIn('id',SalesTeamMember::where('user_id',$user->id)->select('sales_team_id'))->pluck('name')->implode(', '), 'assigned'=>Lead::withoutGlobalScopes()->where('assigned_to',$user->id)->count(),
                'today'=>$today, 'pending'=>Lead::withoutGlobalScopes()->where('assigned_to',$user->id)->whereIn('current_stage',['new','assigned','follow_up'])->count(),
                'unassigned'=>$pending, 'remaining'=>$settings['daily_limit'] ? max(0,$settings['daily_limit']-$today) : null,
                'available'=>$user->status === 'active' && ($settings['members'][$user->id]['available'] ?? true)
                    && SalesTeamMember::whereIn('sales_team_id',$this->assignmentTeams($user->organization_id)->when($settings['team_id'],fn ($q) => $q->whereKey($settings['team_id']))->select('id'))->where('user_id',$user->id)->where('is_active',true)->exists()];
        })->all();
    }

    public function assignmentPendingLeads(int $orgId)
    {
        $settings = $this->assignmentDefaults(Organization::findOrFail($orgId));
        return Lead::withoutGlobalScopes()->whereIn('client_id',User::where('organization_id',$orgId)->select('id'))
            ->when($settings['project_ids'], fn ($q) => $q->whereIn('campaign_id', \App\Models\Campaign::withoutGlobalScopes()->whereIn('project_id',$settings['project_ids'])->select('id')))
            ->when($settings['campaign_ids'], fn ($q) => $q->whereIn('campaign_id',$settings['campaign_ids']))
            ->where('archive_assignment_hold', false)->whereNull('assigned_to')->where('delivery_pending',false)->where('internal_processing_pending',false)->whereNotNull('delivered_at')
            ->whereNotIn('status',['duplicate','invalid','rejected','lost','closed_won','replaced']);
    }

    protected function distributeConfiguredLead(Lead $lead, int $orgId): array
    {
        return DB::transaction(function () use ($lead,$orgId) {
            // One organization lock serializes selection and quota consumption across workers.
            $org = Organization::whereKey($orgId)->lockForUpdate()->firstOrFail();
            $lead = Lead::withoutGlobalScopes()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $s = $this->assignmentDefaults($org);
            $result = ['assigned_user'=>null,'method'=>$s['method'],'status'=>'unassigned','message'=>'Lead is waiting for an eligible executive.'];
            if ($lead->assigned_to) {
                $user = User::find($lead->assigned_to);
                if ($user && $user->organization_id === $orgId) $this->assignLeadToUser($lead,$user,'existing');
                return array_replace($result,['assigned_user'=>$user,'status'=>'assigned','message'=>'Already assigned.']);
            }
            $isManual = ($s['assignment_mode'] ?? 'automatic') === Organization::ASSIGNMENT_MODE_MANUAL
                || $org->isManualAssignment()
                || !$s['enabled']
                || $s['method'] === 'manual'
                || !$org->auto_assign_enabled;

            if ($isManual) {
                if (!$lead->client_id) {
                    $builderUser = $org->clients()->whereHas('roles', fn ($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder', 'builder-admin', 'Client', 'client']))->first();
                    if ($builderUser) {
                        $lead->update(['client_id' => $builderUser->id]);
                    }
                }
                return array_replace($result, [
                    'status' => 'held_for_builder_manual_assignment',
                    'message' => 'Lead routed directly to Builder / Developer account for manual assignment.',
                ]);
            }

            $local = now()->setTimezone($s['timezone']);
            if (!$org->is_active
                || !$lead->delivered_at || $lead->delivery_pending || $lead->internal_processing_pending
                || !in_array($local->dayOfWeek,$s['working_days']) || $local->format('H:i') < $s['start_time'] || $local->format('H:i') > $s['end_time']
                || in_array($lead->status,['duplicate','invalid','rejected','lost','closed_won','replaced'])) return $result;
            $projectId = $lead->campaign?->project_id ?: $lead->project_id;
            if (($s['project_ids'] && !in_array($projectId,$s['project_ids'])) || ($s['campaign_ids'] && !in_array($lead->campaign_id,$s['campaign_ids']))) return $result;
            [$start,$end] = $this->assignmentDay($s);
            $teams = $this->assignmentTeams($orgId)->when($s['team_id'],fn ($q) => $q->whereKey($s['team_id']))->pluck('id');
            $activeMembers = SalesTeamMember::whereIn('sales_team_id',$teams)->where('is_active',true)->pluck('user_id');
            $candidates = $this->assignmentExecutives($orgId)->where('status','active')->whereIn('id',$activeMembers)->get()->filter(function ($user) use ($s,$start,$end) {
                return ($s['members'][$user->id]['available'] ?? true) && (!$s['daily_limit'] || LeadAssignment::where('assigned_to',$user->id)
                    ->where('assigned_at','>=',$start)->where('assigned_at','<',$end)->count() < $s['daily_limit']);
            });
            if ($candidates->isEmpty()) return $result;
            $selected = match ($s['method']) {
                'priority' => $candidates->sortBy(fn ($u) => $s['members'][$u->id]['priority'] ?? PHP_INT_MAX)->first(),
                'equal_distribution' => $candidates->sortBy(fn ($u) => Lead::withoutGlobalScopes()->where('assigned_to',$u->id)->count())->first(),
                default => $this->pickRoundRobin($candidates),
            };
            $this->assignLeadToUser($lead,$selected,$s['method']);
            AuditLog::create(['organization_id'=>$orgId,'user_id'=>auth()->id(),'action'=>'lead.auto_assigned',
                'subject_type'=>Lead::class,'subject_id'=>$lead->id,'from_value'=>['assigned_to'=>null], 'to_value'=>['assigned_to'=>$selected->id,'method'=>$s['method']], 'created_at'=>now()]);
            return array_replace($result,['assigned_user'=>$selected,'status'=>'assigned','message'=>'Lead assigned to '.$selected->name]);
        },3);
    }

    public function processOrganizationAssignments(int $orgId): int
    {
        $org = Organization::findOrFail($orgId);
        $settings = $this->assignmentDefaults($org);
        if (!$settings['enabled'] || ($settings['assignment_mode'] ?? 'automatic') === Organization::ASSIGNMENT_MODE_MANUAL || $settings['method']==='manual') return 0;
        $count = 0;
        foreach ($this->assignmentPendingLeads($orgId)->orderBy('id')->limit($settings['batch_size'])->get() as $lead) {
            if ($this->distribute($lead)['status']==='assigned') $count++;
        }
        return $count;
    }
}
