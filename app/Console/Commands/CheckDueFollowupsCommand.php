<?php

namespace App\Console\Commands;

use App\Models\InternalLeadProcessing;
use App\Services\FollowupEscalationService;
use App\Services\LeadDistributionService;
use Illuminate\Console\Command;

class CheckDueFollowupsCommand extends Command
{
    protected $signature = 'followups:check-due';
    protected $description = 'Check, remind, and escalate scheduled lead follow-ups across executive, team leader, and builder tiers.';

    public function handle(FollowupEscalationService $escalationService): int
    {
        // 1. Process Internal Processing queue follow-ups
        InternalLeadProcessing::whereNull('handover_requested_at')
            ->whereNull('forwarded_at')
            ->where('next_followup_at', '<=', now())
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    app(LeadDistributionService::class)->internalAlert(
                        $record,
                        'internal_followup_due',
                        'Internal follow-up is due',
                        'followup:' . $record->id . ':' . $record->next_followup_at->toIso8601String()
                    );
                }
            });

        // 2. Process all sales follow-up reminders and escalations
        $results = $escalationService->process();

        $totalNotified = array_sum($results);
        $this->info(sprintf(
            'Follow-up engine processed: %d actions (Upcoming: %d, Due: %d, Overdue: %d, TL Escalated: %d, Admin Escalated: %d).',
            $totalNotified,
            $results['upcoming'],
            $results['due'],
            $results['overdue_executive'],
            $results['escalated_tl'],
            $results['escalated_admin']
        ));

        return Command::SUCCESS;
    }
}
