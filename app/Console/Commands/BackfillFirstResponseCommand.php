<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadMessage;
use App\Models\LeadCommunication;
use App\Models\LeadStatusHistory;

class BackfillFirstResponseCommand extends Command
{
    protected $signature = 'leads:backfill-first-response';

    protected $description = 'Infer and backfill first_response_at timestamps for historical leads based on earliest activity';

    public function handle(): int
    {
        $this->info('Starting first_response_at SLA backfill process...');

        $unrespondedLeads = Lead::whereNull('first_response_at')->get();
        $updatedCount = 0;

        foreach ($unrespondedLeads as $lead) {
            $earliestTimestamps = [];

            // Earliest Call
            $earliestCall = LeadCall::where('lead_id', $lead->id)->min('created_at');
            if ($earliestCall) {
                $earliestTimestamps[] = $earliestCall;
            }

            // Earliest Outbound Message
            $earliestMessage = LeadMessage::where('lead_id', $lead->id)
                ->where('direction', 'outbound')
                ->min('created_at');
            if ($earliestMessage) {
                $earliestTimestamps[] = $earliestMessage;
            }

            // Earliest Outbound Communication
            $earliestComm = LeadCommunication::where('lead_id', $lead->id)
                ->where('direction', 'outbound')
                ->min('created_at');
            if ($earliestComm) {
                $earliestTimestamps[] = $earliestComm;
            }

            // Earliest Stage Transition
            $earliestStageChange = LeadStatusHistory::where('lead_id', $lead->id)
                ->whereNotIn('stage', ['new', 'assigned', 'pending_credit'])
                ->min('created_at');
            if ($earliestStageChange) {
                $earliestTimestamps[] = $earliestStageChange;
            }

            if (!empty($earliestTimestamps)) {
                sort($earliestTimestamps);
                $firstTimestamp = $earliestTimestamps[0];

                $lead->update([
                    'first_response_at' => $firstTimestamp,
                ]);

                $updatedCount++;
            }
        }

        $this->info("First response SLA backfill completed! Updated {$updatedCount} historical lead records.");

        return Command::SUCCESS;
    }
}
