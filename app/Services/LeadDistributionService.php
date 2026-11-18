<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\Project;
use App\Models\Organization;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\DistributionRule;
use App\Models\LeadAssignment;
use App\Models\LeadStatusHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadDistributionService
{
    use \App\Services\Concerns\ProcessesInternalLeads;
    use \App\Services\Concerns\ProcessesClientDelivery;
    use \App\Services\Concerns\ConfiguresTeamAssignment;
    public function effectiveDeliveryConfig(\App\Models\Campaign $campaign, ?Project $project = null): array
    {
        $project ??= Project::withoutGlobalScopes()->findOrFail($campaign->project_id);
        return [
            'limit' => (int) ($campaign->delivery_daily_limit ?? $project->delivery_daily_limit ?? 0),
            'interval' => (int) ($campaign->delivery_interval_minutes ?? $project->delivery_interval_minutes ?? 0),
            'timezone' => User::find($project->client_id)?->organization?->delivery_timezone ?: config('app.timezone'),
            'limit_source' => $campaign->delivery_daily_limit === null ? 'Project default' : 'Campaign override',
            'interval_source' => $campaign->delivery_interval_minutes === null ? 'Project default' : 'Campaign override',
        ];
    }

    private function campaignDeliveryQuery(int $campaignId): \Illuminate\Database\Eloquent\Builder
    {
        return Lead::withoutGlobalScopes()->where('campaign_id', $campaignId)
            ->whereNotIn('status', ['duplicate', 'rejected', 'invalid'])
            ->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid']);
    }

    private function deliveryDay($at, string $timezone): array
    {
        $local = $at->copy()->setTimezone($timezone)->startOfDay();
        return [$local->copy()->setTimezone(config('app.timezone')), $local->addDay()->setTimezone(config('app.timezone'))];
    }

    private function deliveryRecipientBelongsToProject(Lead $lead, Project $project): bool
    {
        if ($lead->client_id == $project->client_id) return true;
        $owner = User::find($project->client_id);
        $recipient = User::find($lead->client_id);
        return $owner?->organization_id && $owner->organization_id == $recipient?->organization_id;
    }

    public function scheduleDelivery(Lead $lead, bool $internalHandover = false): void
    {
        $organization = User::find($lead->client_id)?->organization;
        if (! $internalHandover && $organization?->internal_processing_enabled) {
            $lead->internal_processing_pending = true;
            $lead->delivery_pending = true;
            $lead->delivery_at = null;
            $lead->assigned_to = null;
            return;
        }
        if (!$internalHandover && !$lead->exists && $organization && ($plan = \App\Models\LeadDeliveryPlan::where('organization_id', $organization->id)->first())) {
            // New intake must not bypass this organization's configured batch/manual schedule.
            $lead->delivery_plan_id = $plan->id;
            $lead->delivery_pending = true;
            $lead->delivery_at = null;
            $lead->assigned_to = null;
            return;
        }
        $campaign = \App\Models\Campaign::withoutGlobalScopes()->find($lead->campaign_id);
        if (! $campaign) {
            if ($internalHandover) {
                $lead->delivery_at = now();
                $lead->delivery_pending = true;
            }
            return;
        }
        $project = Project::withoutGlobalScopes()->whereKey($campaign->project_id)->lockForUpdate()->first();
        if (! $project) return;
        if (! $this->deliveryRecipientBelongsToProject($lead, $project)) {
            throw new \DomainException('Lead recipient does not belong to the campaign organization.');
        }
        $config = $this->effectiveDeliveryConfig($campaign, $project);
        $queue = $this->campaignDeliveryQuery($campaign->id);
        $at = now();
        $lastDelivered = (clone $queue)->max('delivered_at');
        $lastScheduled = (clone $queue)->where('delivery_pending', true)->whereNull('delivery_failed_at')->max('delivery_at');
        foreach ([$lastDelivered, $lastScheduled] as $last) {
            if ($config['interval'] && $last) {
                $next = \Carbon\Carbon::parse($last)->addMinutes($config['interval']);
                if ($next->gt($at)) $at = $next;
            }
        }
        if ($config['limit']) {
            do {
                [$start, $end] = $this->deliveryDay($at, $config['timezone']);
                $successful = (clone $queue)->where('delivered_at', '>=', $start)->where('delivered_at', '<', $end)->count();
                // Reserve planning slots without counting pending leads as successful deliveries.
                $reserved = (clone $queue)->where('delivery_pending', true)->whereNull('delivery_failed_at')
                    ->where('delivery_at', '>=', $start)->where('delivery_at', '<', $end)->count();
                if ($successful + $reserved < $config['limit']) break;
                $at = $end;
            } while (true);
        }
        $lead->delivery_at = $at;
        $lead->delivery_config = $config;
        $lead->delivery_pending = true;
    }

    public function releaseDelivery(int $leadId, ?int $deliveryRunId = null): bool
    {
        try {
            return DB::transaction(function () use ($leadId, $deliveryRunId) {
                $candidate = Lead::withoutGlobalScopes()->find($leadId);
                if ($candidate) Organization::whereKey($this->resolveLeadOrganizationId($candidate))->whereNotNull('assignment_config')->lockForUpdate()->first();
                $campaign = $candidate ? \App\Models\Campaign::withoutGlobalScopes()->find($candidate->campaign_id) : null;
                $project = $campaign ? Project::withoutGlobalScopes()->whereKey($campaign->project_id)->lockForUpdate()->first() : null;
                $lead = Lead::withoutGlobalScopes()->whereKey($leadId)->lockForUpdate()->first();
                if ($lead && !$this->plannedReleaseAllowed($lead, $deliveryRunId)) return false;
                if ($lead?->internal_processing_pending) {
                    $processing = \App\Models\InternalLeadProcessing::where('lead_id', $lead->id)->lockForUpdate()->first();
                    if (! $processing?->handover_requested_at || $processing->forwarded_at) return false;
                }
                if (! $lead || ! $lead->delivery_pending || $lead->delivery_at?->isFuture()) return false;
                if ($project && ! $this->deliveryRecipientBelongsToProject($lead, $project)) {
                    $lead->update(['delivery_failed_at' => now(), 'delivery_error' => 'Lead recipient does not belong to the campaign organization.']);
                    return false;
                }
                if (in_array(strtolower($lead->status), ['duplicate', 'rejected', 'invalid']) || in_array(strtolower($lead->current_stage), ['duplicate', 'rejected', 'invalid'])) {
                    // Keep undelivered rejected records hidden from the customer panel.
                    return false;
                }
                if ($project && (! $project->delivery_enabled || $project->status !== 'active' || $campaign->status !== 'active')) return false;
                if ($project) {
                    $config = $lead->delivery_config ?? $this->effectiveDeliveryConfig($campaign, $project);
                    $queue = $this->campaignDeliveryQuery($campaign->id);
                    [$start, $end] = $this->deliveryDay(now(), $config['timezone']);
                    $successful = (clone $queue)->where('delivered_at', '>=', $start)->where('delivered_at', '<', $end)->count();
                    if ($config['limit'] && $successful >= $config['limit']) {
                        app(NotificationDeliveryService::class)->leadAlert($lead, 'daily_limit_reached', 'Daily delivery limit reached',
                            "Campaign daily limit reached. Pending leads are waiting for the next delivery day.", 'limit:'.$campaign->id.':'.$start->toDateString(), 'delivery', true);
                        return false;
                    }
                    $last = (clone $queue)->max('delivered_at');
                    if ($config['interval'] && $last && \Carbon\Carbon::parse($last)->addMinutes($config['interval'])->isFuture()) return false;
                }
                if (! \App\Models\CreditTransaction::reserveForLead($lead)) {
                    $lead->update(['delivery_failed_at' => now(), 'delivery_error' => 'Credit reservation failed: check organization status and available balance.']);
                    if (isset($processing)) $this->internalForwardingFailed($processing, $lead->delivery_error);
                    return false;
                }
                $lead->update(['delivery_pending' => false, 'delivered_at' => now(), 'delivery_failed_at' => null, 'delivery_error' => null, 'internal_processing_pending' => false]);
                if (Organization::find($this->resolveLeadOrganizationId($lead))?->assignment_config !== null) $this->distribute($lead);
                if (isset($processing)) {
                    $processing->update(['forwarded_at' => now(), 'forwarded_by' => $processing->handover_requested_by, 'status_code' => 'forwarded_to_client', 'forwarding_error' => null]);
                    $this->internalEvent($processing, 'forwarded', ['after' => ['forwarded_at' => $processing->forwarded_at->toIso8601String(), 'assigned_to' => $lead->assigned_to]], User::find($processing->forwarded_by));
                    app(NotificationDeliveryService::class)->leadAlert($lead, 'internal_forwarded', 'Verified lead forwarded to your organization', "Lead {$lead->lead_code} was verified by Lead Panther and handed over.", 'internal-forwarded:'.$processing->id, 'delivery');
                    $this->internalAlert($processing, 'internal_forwarded', 'Lead successfully handed over to client', 'forwarded:'.$processing->id);
                }
                if ($project && $config['limit'] && $successful + 1 >= $config['limit']) {
                    app(NotificationDeliveryService::class)->leadAlert($lead, 'daily_limit_reached', 'Daily delivery limit reached',
                        'Campaign daily delivery capacity has been used. Further leads will wait for the next available day.',
                        'limit:'.$campaign->id.':'.$start->toDateString(), 'delivery', true);
                }
                return true;
            }, 3);
        } catch (\Throwable $e) {
            Lead::withoutGlobalScopes()->whereKey($leadId)->where('delivery_pending', true)
                ->update(['delivery_failed_at' => now(), 'delivery_error' => 'Delivery failed; see application log.']);
            report($e);
            if ($record = \App\Models\InternalLeadProcessing::where('lead_id', $leadId)->whereNotNull('handover_requested_at')->whereNull('forwarded_at')->first()) $this->internalForwardingFailed($record, 'Forwarding failed. See delivery logs.');
            return false;
        }
    }

    public function deliveryStatistics(Project $project, ?\App\Models\Campaign $campaign = null, ?array $campaignIds = null): array
    {
        $timezone = User::find($project->client_id)?->organization?->delivery_timezone ?: config('app.timezone');
        [$start, $end] = $this->deliveryDay(now(), $timezone);
        $query = Lead::withoutGlobalScopes()->whereIn('campaign_id', \App\Models\Campaign::withoutGlobalScopes()->where('project_id', $project->id)->select('id'));
        if ($campaignIds !== null) $query->whereIn('campaign_id', $campaignIds);
        if ($campaign) $query->where('campaign_id', $campaign->id);
        $successful = (clone $query)->whereNotIn('status', ['duplicate', 'rejected', 'invalid'])->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid']);
        $today = (clone $successful)->where('delivered_at', '>=', $start)->where('delivered_at', '<', $end)->count();
        $pending = (clone $successful)->where('delivery_pending', true);
        $limit = $campaign ? $this->effectiveDeliveryConfig($campaign, $project)['limit'] : (int) $project->delivery_daily_limit;
        $remaining = $limit ? max(0, $limit - $today) : null;
        if (! $campaign) {
            $capacities = \App\Models\Campaign::withoutGlobalScopes()->where('project_id', $project->id)->get()
                ->when($campaignIds !== null, fn ($items) => $items->whereIn('id', $campaignIds))
                ->map(fn ($item) => $this->deliveryStatistics($project, $item)['remaining']);
            $remaining = $capacities->containsStrict(null) ? null : $capacities->sum();
        }
        return ['total' => (clone $query)->count(), 'today' => $today, 'remaining' => $remaining,
            'pending' => (clone $pending)->count(), 'failed' => (clone $pending)->whereNotNull('delivery_failed_at')->count(),
            'next' => (clone $pending)->min('delivery_at'), 'last' => (clone $successful)->max('delivered_at'), 'timezone' => $timezone];
    }

    /**
     * Supported distribution methods.
     */
    public const METHOD_ROUND_ROBIN = 'round_robin';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_LOCATION = 'location';
    public const METHOD_PRIORITY = 'priority';
    public const METHOD_TEAM = 'team';
    public const METHOD_CAPACITY = 'capacity';

    /**
     * Distribute lead organization-securely based on project rule or explicit method.
     *
     * @return array{assigned_user: ?User, method: string, status: string, message: string}
     */
    public function distribute(Lead $lead, ?string $forceMethod = null, array $overrideConfig = []): array
    {
        $organizationId = $this->resolveLeadOrganizationId($lead);
        if (Organization::find($organizationId)?->assignment_config !== null) {
            return $this->distributeConfiguredLead($lead, $organizationId);
        }
        if ($existing = $lead->fresh()?->assignedTo) {
            $this->assignLeadToUser($lead, $existing, 'existing');
            return ['assigned_user' => $existing, 'method' => 'existing', 'status' => 'assigned', 'message' => 'Lead already assigned.'];
        }
        // 1. Resolve Lead's Target Organization
        $targetOrgId = $this->resolveLeadOrganizationId($lead);
        $targetOrg = $targetOrgId ? Organization::find($targetOrgId) : null;

        // 2. Check if Auto-Assignment is Disabled for Organization
        if ($targetOrg && !$targetOrg->auto_assign_enabled && empty($forceMethod)) {
            return [
                'assigned_user' => null,
                'method' => self::METHOD_MANUAL,
                'status' => 'held_for_manual_assignment',
                'message' => "Automatic assignment disabled for {$targetOrg->name}. Lead placed in unassigned queue.",
            ];
        }

        // 3. Fetch Active Distribution Rule for project (or organization default)
        $rule = null;
        if ($lead->project_id) {
            $rule = DistributionRule::where('project_id', $lead->project_id)
                ->where('is_active', true)
                ->latest('id')
                ->first();
        }

        $orgDefaultMethod = $targetOrg?->distribution_method ?: self::METHOD_ROUND_ROBIN;
        $method = $forceMethod ?: ($rule?->rule_type ?: $orgDefaultMethod);
        $config = array_merge($rule?->config ?? [], $overrideConfig);

        // 4. If Manual distribution, hold unassigned
        if ($method === self::METHOD_MANUAL) {
            return [
                'assigned_user' => null,
                'method' => self::METHOD_MANUAL,
                'status' => 'held_for_manual_assignment',
                'message' => 'Lead routed to manual assignment pool.',
            ];
        }

        // 5. Fetch Organization-Secure Candidate Agents
        $candidates = $this->getOrganizationAgents($targetOrgId);

        if ($candidates->isEmpty()) {
            return [
                'assigned_user' => null,
                'method' => $method,
                'status' => 'unassigned',
                'message' => 'No eligible organization team members available. Kept in unassigned queue.',
            ];
        }

        // 5. Apply Selected Distribution Method
        $selectedUser = match ($method) {
            self::METHOD_LOCATION => $this->pickLocationWise($candidates, $lead, $config),
            self::METHOD_PRIORITY => $this->pickPriorityWise($candidates, $config),
            self::METHOD_TEAM => $this->pickTeamWise($candidates, $lead, $config, $targetOrgId),
            self::METHOD_CAPACITY => $this->pickCapacityWise($candidates),
            default => $this->pickRoundRobin($candidates),
        };

        if (!$selectedUser) {
            // Fallback to round-robin among candidates
            $selectedUser = $this->pickRoundRobin($candidates);
        }

        if ($selectedUser) {
            $this->assignLeadToUser($lead, $selectedUser, $method);

            return [
                'assigned_user' => $selectedUser,
                'method' => $method,
                'status' => 'assigned',
                'message' => "Lead securely assigned to {$selectedUser->name} via " . ucwords(str_replace('_', ' ', $method)) . ".",
            ];
        }

        return [
            'assigned_user' => null,
            'method' => $method,
            'status' => 'unassigned',
            'message' => 'Failed to resolve eligible agent.',
        ];
    }

    /**
     * Resolve Organization ID for the given lead strictly to avoid cross-tenant leaks.
     */
    public function resolveLeadOrganizationId(Lead $lead): ?int
    {
        if ($lead->client && $lead->client->organization_id) {
            return $lead->client->organization_id;
        }

        if ($lead->client_id) {
            $client = DB::table('users')->where('id', $lead->client_id)->first();
            if ($client?->organization_id) {
                return (int) $client->organization_id;
            }
        }

        if ($lead->project_id) {
            $project = DB::table('projects')
                ->join('users', 'projects.client_id', '=', 'users.id')
                ->where('projects.id', $lead->project_id)
                ->select('users.organization_id')
                ->first();

            if ($project?->organization_id) {
                return (int) $project->organization_id;
            }
        }

        // Fallback to auth user org or platform HQ
        return auth()->user()?->organization_id ?: (int) (DB::table('organizations')->value('id') ?? 1);
    }

    /**
     * Retrieve active sales agents belonging STRICTLY to the target organization.
     *
     * @return Collection<User>
     */
    public function getOrganizationAgents(?int $organizationId): Collection
    {
        $query = User::query()
            ->where('status', 'active');

        $query->where('organization_id', $organizationId);

        $salesRoles = ['sales-executive', 'Sales Executive', 'sales_executive', 'Executive', 'Channel Partner', 'Broker'];

        $agents = $query->whereHas('roles', function ($q) use ($salesRoles) {
            $q->whereIn('name', $salesRoles);
        })->get();

        // No cross-organization fallback: keep the lead unassigned if this client has no agents.
        return $agents;
    }

    /**
     * Method 1: Round-Robin Distribution.
     * Picks agent in candidate pool who was least recently assigned.
     */
    protected function pickRoundRobin(Collection $candidates): ?User
    {
        if ($candidates->isEmpty()) return null;
        if ($candidates->count() === 1) return $candidates->first();

        $candidateIds = $candidates->pluck('id')->toArray();

        // Find the last assigned agent among candidate IDs
        $lastAssignment = LeadAssignment::whereIn('assigned_to', $candidateIds)
            ->latest('id')
            ->first();

        if (!$lastAssignment) {
            return $candidates->first();
        }

        $currentIndex = array_search($lastAssignment->assigned_to, $candidateIds);
        $nextIndex = ($currentIndex !== false && isset($candidateIds[$currentIndex + 1])) ? $currentIndex + 1 : 0;

        return $candidates->firstWhere('id', $candidateIds[$nextIndex]) ?? $candidates->first();
    }

    /**
     * Method 2: Location-Based Distribution.
     * Matches lead city or location against mapped agent territories.
     */
    protected function pickLocationWise(Collection $candidates, Lead $lead, array $config): ?User
    {
        $locationMap = $config['location_map'] ?? [];
        $leadCity = strtolower(trim($lead->city ?: ($lead->location ?: '')));

        if (!empty($leadCity) && !empty($locationMap)) {
            foreach ($locationMap as $row) {
                $targetCity = strtolower(trim($row['city'] ?? ''));
                if ($targetCity === $leadCity && !empty($row['user_id'])) {
                    $matched = $candidates->firstWhere('id', (int) $row['user_id']);
                    if ($matched) return $matched;
                }
            }
        }

        return null;
    }

    /**
     * Method 3: Priority-Based Distribution.
     * Selects from ordered priority list of agents if available.
     */
    protected function pickPriorityWise(Collection $candidates, array $config): ?User
    {
        $priorityUserIds = $config['priority_users'] ?? [];

        foreach ($priorityUserIds as $userId) {
            $user = $candidates->firstWhere('id', (int) $userId);
            if ($user) return $user;
        }

        return null;
    }

    /**
     * Method 4: Team-Based Distribution.
     * Filters candidates to a designated sales team within the organization.
     */
    protected function pickTeamWise(Collection $candidates, Lead $lead, array $config, ?int $orgId): ?User
    {
        $teamId = $config['sales_team_id'] ?? null;
        if (!$teamId) {
            // Check if there is an active sales team for this organization
            $teamId = SalesTeam::where('ownable_type', Organization::class)
                ->where('ownable_id', $orgId)
                ->value('id');
        }

        if ($teamId) {
            $teamMemberUserIds = SalesTeamMember::where('sales_team_id', $teamId)
                ->where('is_active', true)
                ->pluck('user_id')
                ->toArray();

            $teamCandidates = $candidates->whereIn('id', $teamMemberUserIds);
            if ($teamCandidates->isNotEmpty()) {
                return $this->pickRoundRobin($teamCandidates);
            }
        }

        return null;
    }

    /**
     * Method 5: Capacity-Based Distribution.
     * Calculates active leads per agent and assigns to the agent with lowest capacity load.
     */
    protected function pickCapacityWise(Collection $candidates): ?User
    {
        if ($candidates->isEmpty()) return null;

        $terminalStages = ['booking', 'payment', 'closed_won', 'closed_lost', 'wrong_number', 'duplicate', 'rental', 'replaced'];

        $bestAgent = null;
        $lowestLoadRatio = PHP_FLOAT_MAX;

        foreach ($candidates as $agent) {
            $activeCount = Lead::where('assigned_to', $agent->id)
                ->whereNotIn('current_stage', $terminalStages)
                ->count();

            // Check if member has specific max_capacity in team
            $maxCapacity = (int) (SalesTeamMember::where('user_id', $agent->id)->value('max_capacity') ?? 25);
            if ($maxCapacity <= 0) $maxCapacity = 25;

            // If agent is already at or above capacity, skip unless everyone is at capacity
            $ratio = $activeCount / $maxCapacity;

            if ($ratio < $lowestLoadRatio && $activeCount < $maxCapacity) {
                $lowestLoadRatio = $ratio;
                $bestAgent = $agent;
            }
        }

        return $bestAgent ?? $this->pickRoundRobin($candidates);
    }

    /**
     * Execute assignment mutation, log history and audit record.
     */
    public function assignLeadToUser(Lead $lead, User $user, string $method): void
    {
        DB::transaction(function () use ($lead, $user, $method) {
            $lead = Lead::withoutGlobalScopes()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($lead->assigned_to === $user->id && LeadAssignment::where('lead_id',$lead->id)->where('assigned_to',$user->id)->whereNull('unassigned_at')->exists()) return;
            $previousAssigneeId = $lead->assigned_to;
            $oldStage = $lead->current_stage;

            // Unassign previous if active
            if ($previousAssigneeId && $previousAssigneeId !== $user->id) {
                LeadAssignment::where('lead_id', $lead->id)
                    ->where('assigned_to', $previousAssigneeId)
                    ->whereNull('unassigned_at')
                    ->update(['unassigned_at' => now()]);
            }

            $assignerId = auth()->id() ?? User::where('id', 1)->value('id') ?? User::value('id') ?? $user->id;

            $newStage = ($oldStage === 'new' || empty($oldStage)) ? 'assigned' : $oldStage;

            $lead->webAssignmentRecorded = true;
            try {
                $lead->update([
                    'assigned_to' => $user->id,
                    'current_stage' => $newStage,
                    'status' => $newStage,
                ]);
            } finally {
                $lead->webAssignmentRecorded = false;
            }

            // Persist the recipient before the assignment observer checks lead access.
            LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $user->id,
                'assigned_by' => $assignerId,
                'assigned_at' => now(),
            ]);

            // Status transition history
            LeadStatusHistory::create([
                'lead_id' => $lead->id,
                'from_status' => $oldStage ?: 'new',
                'to_status' => $newStage,
                'changed_by' => $assignerId,
                'changed_at' => now(),
            ]);
        });
    }
}
