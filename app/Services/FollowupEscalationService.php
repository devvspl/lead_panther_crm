<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\User;
use App\Notifications\FollowupDueNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

class FollowupEscalationService
{
    protected NotificationDeliveryService $notificationService;

    public function __construct(NotificationDeliveryService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Run all follow-up reminder checks and overdue escalations.
     *
     * @return array Summary of processed actions
     */
    public function process(): array
    {
        $counts = [
            'upcoming' => $this->sendUpcomingReminders(),
            'due' => $this->sendDueReminders(),
            'overdue_executive' => $this->sendOverdueExecutiveAlerts(),
            'escalated_tl' => $this->escalateToTeamLeaders(),
            'escalated_admin' => $this->escalateToAdmins(),
        ];

        return $counts;
    }

    /**
     * Send upcoming follow-up reminders (due in the next 30 minutes).
     */
    public function sendUpcomingReminders(): int
    {
        $followups = Followup::pending()
            ->whereBetween('due_at', [now(), now()->addMinutes(30)])
            ->get();

        $count = 0;
        foreach ($followups as $followup) {
            $lead = Lead::withoutGlobalScopes()->find($followup->lead_id);
            if (! $lead || $lead->delivery_pending || $lead->internal_processing_pending) {
                continue;
            }

            $executive = $lead->assigned_to ? User::find($lead->assigned_to) : null;
            if (! $executive || $executive->status !== 'active') {
                continue;
            }

            $orgId = $executive->organization_id ?? User::find($lead->client_id)?->organization_id;
            $eventKey = 'followup_due_soon:' . $followup->id . ':' . $followup->due_at->format('Y-m-d H:i');
            $time = $followup->due_at->format('h:i A');

            $notification = $this->notificationService->sendWeb(
                $executive,
                'followup_due_soon',
                'Upcoming Follow-up: ' . $lead->name,
                "Follow-up call with {$lead->name} ({$lead->lead_code}) is scheduled at {$time} today.",
                $eventKey,
                'followup',
                $orgId,
                'lead',
                $lead->id,
                important: false,
                context: ['lead_id' => $lead->id, 'lead_code' => $lead->lead_code, 'followup_id' => $followup->id]
            );

            if ($notification && $notification->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Send due-now reminders for follow-ups whose due_at has arrived.
     */
    public function sendDueReminders(): int
    {
        $followups = Followup::pending()
            ->where('due_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($followups as $followup) {
            $lead = Lead::withoutGlobalScopes()->find($followup->lead_id);
            if (! $lead || $lead->delivery_pending || $lead->internal_processing_pending) {
                continue;
            }

            $executive = $lead->assigned_to ? User::find($lead->assigned_to) : null;
            if (! $executive || $executive->status !== 'active') {
                continue;
            }

            $orgId = $executive->organization_id ?? User::find($lead->client_id)?->organization_id;
            $eventKey = 'followup:' . $followup->id . ':' . $followup->due_at->toDateTimeString();

            $notification = $this->notificationService->sendWeb(
                $executive,
                'followup_due',
                'Follow-up Due: ' . $lead->name,
                "Scheduled follow-up with {$lead->name} ({$lead->lead_code}) is now due.",
                $eventKey,
                'followup',
                $orgId,
                'lead',
                $lead->id,
                important: true,
                context: ['lead_id' => $lead->id, 'lead_code' => $lead->lead_code, 'followup_id' => $followup->id]
            );

            $alreadyNotified = DatabaseNotification::where('event_key', hash('sha256', $executive->id . '|' . $eventKey))->exists();
            if ($notification && ! $alreadyNotified && $this->notificationService->webAllowed($executive, 'followup', false)) {
                try {
                    $executive->notify(new FollowupDueNotification($followup));
                } catch (\Throwable $e) {
                    Log::warning('FollowupDueNotification dispatch error: ' . $e->getMessage());
                }
            }

            if ($notification && $notification->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Send overdue reminder alert to assigned executive (overdue by > 15 minutes).
     */
    public function sendOverdueExecutiveAlerts(): int
    {
        $followups = Followup::pending()
            ->where('due_at', '<=', now()->subMinutes(15))
            ->get();

        $count = 0;
        foreach ($followups as $followup) {
            $lead = Lead::withoutGlobalScopes()->find($followup->lead_id);
            if (! $lead || $lead->delivery_pending || $lead->internal_processing_pending) {
                continue;
            }

            $executive = $lead->assigned_to ? User::find($lead->assigned_to) : null;
            if (! $executive || $executive->status !== 'active') {
                continue;
            }

            $orgId = $executive->organization_id ?? User::find($lead->client_id)?->organization_id;
            $eventKey = 'followup_overdue:' . $followup->id . ':' . $followup->due_at->toDateTimeString();

            $notification = $this->notificationService->sendWeb(
                $executive,
                'followup_overdue',
                'OVERDUE: Follow-up Missed for ' . $lead->name,
                "Follow-up for {$lead->name} ({$lead->lead_code}) was due at " . $followup->due_at->format('M d, H:i') . " and is now overdue. Please contact the client immediately.",
                $eventKey,
                'followup',
                $orgId,
                'lead',
                $lead->id,
                important: true,
                context: ['lead_id' => $lead->id, 'lead_code' => $lead->lead_code, 'followup_id' => $followup->id]
            );

            if ($notification && $notification->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Escalate overdue follow-ups (> 2 hours) to Team Leader.
     */
    public function escalateToTeamLeaders(): int
    {
        $followups = Followup::pending()
            ->where('due_at', '<=', now()->subHours(2))
            ->where('escalation_level', '<', 1)
            ->get();

        $count = 0;
        foreach ($followups as $followup) {
            $lead = Lead::withoutGlobalScopes()->find($followup->lead_id);
            if (! $lead || $lead->delivery_pending || $lead->internal_processing_pending) {
                continue;
            }

            $orgId = User::find($lead->client_id)?->organization_id ?? User::find($lead->assigned_to)?->organization_id;
            $teamLeader = $this->resolveTeamLeaderFor($lead, $orgId);

            if (! $teamLeader || $teamLeader->status !== 'active') {
                continue;
            }

            $eventKey = 'followup_escalate_tl:' . $followup->id . ':' . $followup->due_at->toDateTimeString();
            $executiveName = $lead->assignedTo?->name ?? 'Unassigned Executive';

            $notification = $this->notificationService->sendWeb(
                $teamLeader,
                'followup_escalated_tl',
                'ESCALATION: Overdue Follow-up - ' . $lead->name,
                "Follow-up for {$lead->name} ({$lead->lead_code}) assigned to {$executiveName} is overdue by >2 hours.",
                $eventKey,
                'followup',
                $orgId,
                'lead',
                $lead->id,
                important: true,
                context: ['lead_id' => $lead->id, 'lead_code' => $lead->lead_code, 'followup_id' => $followup->id, 'executive' => $executiveName]
            );

            if ($notification) {
                $followup->update([
                    'escalation_level' => 1,
                    'escalated_at' => now(),
                    'escalation_notes' => "Escalated to Team Leader {$teamLeader->name} after >2 hours delay.",
                ]);

                AuditLog::create([
                    'user_id' => $teamLeader->id,
                    'organization_id' => $orgId,
                    'action' => 'lead.followup_escalated',
                    'subject_type' => Lead::class,
                    'subject_id' => $lead->id,
                    'from_value' => json_encode(['escalation_level' => 0]),
                    'to_value' => json_encode([
                        'escalation_level' => 1,
                        'tier' => 'team_leader',
                        'escalated_to' => $teamLeader->name,
                        'team_leader_id' => $teamLeader->id,
                        'followup_id' => $followup->id,
                        'overdue_since' => $followup->due_at->toIso8601String(),
                    ]),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'LeadPanther/Scheduler',
                    'created_at' => now(),
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Escalate critical overdue follow-ups (> 24 hours) to Builder / Organization Admin.
     */
    public function escalateToAdmins(): int
    {
        $followups = Followup::pending()
            ->where('due_at', '<=', now()->subHours(24))
            ->where('escalation_level', '<', 2)
            ->get();

        $count = 0;
        foreach ($followups as $followup) {
            $lead = Lead::withoutGlobalScopes()->find($followup->lead_id);
            if (! $lead || $lead->delivery_pending || $lead->internal_processing_pending) {
                continue;
            }

            $orgId = User::find($lead->client_id)?->organization_id ?? User::find($lead->assigned_to)?->organization_id;
            $builder = $this->resolveBuilderFor($lead, $orgId);

            if (! $builder || $builder->status !== 'active') {
                continue;
            }

            $eventKey = 'followup_escalate_admin:' . $followup->id . ':' . $followup->due_at->toDateTimeString();
            $executiveName = $lead->assignedTo?->name ?? 'Unassigned Executive';

            $notification = $this->notificationService->sendWeb(
                $builder,
                'followup_escalated_admin',
                'CRITICAL ESCALATION: 24h+ Overdue Follow-up - ' . $lead->name,
                "Follow-up for {$lead->name} ({$lead->lead_code}) assigned to {$executiveName} remains unresolved after 24 hours.",
                $eventKey,
                'followup',
                $orgId,
                'lead',
                $lead->id,
                important: true,
                context: ['lead_id' => $lead->id, 'lead_code' => $lead->lead_code, 'followup_id' => $followup->id, 'executive' => $executiveName]
            );

            if ($notification) {
                $followup->update([
                    'escalation_level' => 2,
                    'escalated_at' => now(),
                    'escalation_notes' => "Critical escalation to Builder/Admin {$builder->name} after >24 hours delay.",
                ]);

                AuditLog::create([
                    'user_id' => $builder->id,
                    'organization_id' => $orgId,
                    'action' => 'lead.followup_escalated',
                    'subject_type' => Lead::class,
                    'subject_id' => $lead->id,
                    'from_value' => json_encode(['escalation_level' => 1]),
                    'to_value' => json_encode([
                        'escalation_level' => 2,
                        'tier' => 'builder_admin',
                        'escalated_to' => $builder->name,
                        'builder_id' => $builder->id,
                        'followup_id' => $followup->id,
                        'overdue_since' => $followup->due_at->toIso8601String(),
                    ]),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'LeadPanther/Scheduler',
                    'created_at' => now(),
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Resolve Team Leader responsible for the lead or executive.
     */
    protected function resolveTeamLeaderFor(Lead $lead, ?int $organizationId): ?User
    {
        if ($lead->assigned_to) {
            $teamIds = SalesTeamMember::where('user_id', $lead->assigned_to)
                ->where('is_active', true)
                ->pluck('sales_team_id');

            $leaderId = SalesTeam::whereIn('id', $teamIds)->value('leader_id');
            if ($leaderId) {
                $leader = User::find($leaderId);
                if ($leader && $leader->status === 'active') {
                    return $leader;
                }
            }
        }

        if ($organizationId) {
            return User::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Team Leader', 'team-leader', 'team_leader']))
                ->first();
        }

        return null;
    }

    /**
     * Resolve Builder / Developer / Org Admin responsible for the lead.
     */
    protected function resolveBuilderFor(Lead $lead, ?int $organizationId): ?User
    {
        if ($lead->client_id) {
            $client = User::find($lead->client_id);
            if ($client && $client->status === 'active' && $client->hasAnyRole(['Builder/Developer', 'Builder', 'builder', 'Client'])) {
                return $client;
            }
        }

        if ($organizationId) {
            return User::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder']))
                ->first();
        }

        return null;
    }
}
