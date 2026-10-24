<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\LeadAssignment;
use App\Models\CreditTransaction;
use App\Models\LeadReplacement;
use App\Models\LeadCommunication;
use App\Models\LeadCall;
use App\Models\SiteVisit;
use App\Models\Meeting;
use App\Models\Proposal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeadJourneyService
{
    /**
     * Compile and return the complete, chronological lead journey events.
     *
     * @return array{lead: Lead, summary: array, events: array}
     */
    public function getJourney(Lead $lead): array
    {
        $events = collect();

        // 1. Initial Ingestion / Creation
        $events->push([
            'id' => 'ingestion',
            'type' => 'creation',
            'category' => 'Ingestion',
            'timestamp' => $lead->created_at ? Carbon::parse($lead->created_at) : now(),
            'title' => 'Lead Ingested & Captured',
            'details' => "Lead {$lead->lead_code} was captured for Project '{$lead->projectName}' via Source '{$lead->leadSourceName}'. Initial Requirement: " . ($lead->requirement ?: 'Standard inquiry') . ".",
            'icon' => 'sparkles',
            'color' => 'bg-blue-100 text-blue-900 border-blue-300',
            'badge' => 'Ingested',
            'actor' => 'System Ingestion',
        ]);

        // 2. Status & Stage History
        $statusHistory = LeadStatusHistory::where('lead_id', $lead->id)->get();
        foreach ($statusHistory as $hist) {
            $changedAt = $hist->changed_at ? Carbon::parse($hist->changed_at) : Carbon::parse($hist->created_at);
            $events->push([
                'id' => 'status_' . $hist->id,
                'type' => 'status',
                'category' => 'Sales Stage',
                'timestamp' => $changedAt,
                'title' => 'Stage Advanced to ' . ucfirst(str_replace(['_', '-'], ' ', (string)$hist->to_status)),
                'details' => "Moved from '" . ucfirst(str_replace(['_', '-'], ' ', (string)$hist->from_status)) . "' to '" . ucfirst(str_replace(['_', '-'], ' ', (string)$hist->to_status)) . "'.",
                'icon' => 'arrow-path',
                'color' => 'bg-indigo-100 text-indigo-900 border-indigo-300',
                'badge' => ucfirst(str_replace(['_', '-'], ' ', (string)$hist->to_status)),
                'actor' => 'Stage Progression',
            ]);
        }

        // 3. Assignments & Reassignments
        $assignments = LeadAssignment::with(['assignedTo', 'assignedBy'])
            ->where('lead_id', $lead->id)
            ->get();

        foreach ($assignments as $asg) {
            $assignTime = $asg->assigned_at ? Carbon::parse($asg->assigned_at) : Carbon::parse($asg->created_at);
            $agentName = $asg->assignedTo?->name ?: 'Sales Agent';
            $assignerName = $asg->assignedBy?->name ?: 'Distribution Engine';

            $statusText = $asg->unassigned_at 
                ? " (Unassigned at " . Carbon::parse($asg->unassigned_at)->format('M d, H:i') . ")" 
                : " [Active Current Agent]";

            $events->push([
                'id' => 'assignment_' . $asg->id,
                'type' => 'assignment',
                'category' => 'Distribution',
                'timestamp' => $assignTime,
                'title' => "Assigned to {$agentName}",
                'details' => "Lead routed to {$agentName} by {$assignerName}{$statusText}.",
                'icon' => 'user-check',
                'color' => 'bg-purple-100 text-purple-900 border-purple-300',
                'badge' => 'Agent Assigned',
                'actor' => $assignerName,
            ]);
        }

        // 4. Credit Transactions
        $transactions = CreditTransaction::where('lead_id', $lead->id)->get();
        foreach ($transactions as $tx) {
            $txTime = $tx->created_at ? Carbon::parse($tx->created_at) : now();
            $isRefund = $tx->transaction_type === 'refund';
            $sign = $isRefund ? '+' : '-';
            $events->push([
                'id' => 'credit_' . $tx->id,
                'type' => 'credit',
                'category' => 'Credits & Wallet',
                'timestamp' => $txTime,
                'title' => $isRefund ? 'Credit Refund Credited' : 'Lead Credit Fee Deducted',
                'details' => "Transaction #{$tx->id}: {$sign}" . number_format((float)$tx->credit_used, 2) . " Credits. Balance before: " . number_format((float)$tx->credit_before, 2) . ", Balance after: " . number_format((float)$tx->credit_after, 2) . " Credits.",
                'icon' => 'currency-rupee',
                'color' => $isRefund ? 'bg-emerald-100 text-emerald-950 border-emerald-300' : 'bg-amber-100 text-amber-950 border-amber-300',
                'badge' => "{$sign}" . number_format((float)$tx->credit_used, 1) . ' Credits',
                'actor' => 'Wallet Engine',
            ]);
        }

        // 5. SLA Tracking Milestone
        if ($lead->created_at) {
            $created = Carbon::parse($lead->created_at);
            if ($lead->first_response_at) {
                $firstResp = Carbon::parse($lead->first_response_at);
                $diff = $created->diffInMinutes($firstResp);
                $met = $diff <= 15;

                $events->push([
                    'id' => 'sla_met',
                    'type' => 'sla',
                    'category' => 'SLA Performance',
                    'timestamp' => $firstResp,
                    'title' => $met ? 'SLA Target Achieved (Response Time: ' . $diff . 'm)' : 'SLA Target Breached (' . $diff . 'm Delay)',
                    'details' => "First customer contact logged at " . $firstResp->format('M d, H:i') . " ({$diff} minutes after lead arrival, target is ≤ 15 min).",
                    'icon' => 'clock',
                    'color' => $met ? 'bg-emerald-100 text-emerald-950 border-emerald-400' : 'bg-red-100 text-red-950 border-red-300',
                    'badge' => $met ? 'SLA Met (15m)' : 'SLA Breached',
                    'actor' => 'SLA Monitor',
                ]);
            } else {
                $nowDiff = $created->diffInMinutes(now());
                if ($nowDiff > 15) {
                    $events->push([
                        'id' => 'sla_breached',
                        'type' => 'sla',
                        'category' => 'SLA Performance',
                        'timestamp' => $created->copy()->addMinutes(15),
                        'title' => 'SLA Breached — 15m Window Elapsed',
                        'details' => "No first response was recorded within the guaranteed 15-minute SLA threshold. Current elapsed time: {$nowDiff} minutes.",
                        'icon' => 'exclamation-triangle',
                        'color' => 'bg-red-100 text-red-950 border-red-300',
                        'badge' => 'SLA Breached',
                        'actor' => 'SLA Monitor',
                    ]);
                }
            }
        }

        // 6. Replacement Requests
        $replacements = LeadReplacement::with(['reason', 'requestedBy'])
            ->where('lead_id', $lead->id)
            ->get();

        foreach ($replacements as $rep) {
            $repTime = $rep->requested_at ? Carbon::parse($rep->requested_at) : Carbon::parse($rep->created_at);
            $reason = $rep->reason?->label ?: 'Invalid Contact';
            $by = $rep->requestedBy?->name ?: 'Agent';

            $events->push([
                'id' => 'replacement_' . $rep->id,
                'type' => 'replacement',
                'category' => 'Lead Replacement',
                'timestamp' => $repTime,
                'title' => "Replacement Requested: {$reason}",
                'details' => "Replacement request submitted by {$by}. Status: " . ucfirst((string)$rep->status) . ($rep->replacement_lead_id ? " (Replaced by Lead #{$rep->replacement_lead_id})" : "") . ".",
                'icon' => 'arrows-right-left',
                'color' => 'bg-amber-100 text-amber-950 border-amber-300',
                'badge' => 'Replacement ' . ucfirst((string)$rep->status),
                'actor' => $by,
            ]);
        }

        // 7. Communications (SMS, WhatsApp, Email, CRM)
        $comms = LeadCommunication::where('lead_id', $lead->id)->get();
        foreach ($comms as $cm) {
            $cmTime = $cm->sent_at ? Carbon::parse($cm->sent_at) : Carbon::parse($cm->created_at);
            $channelLabel = strtoupper((string)$cm->channel);
            $events->push([
                'id' => 'comm_' . $cm->id,
                'type' => 'communication',
                'category' => 'Multi-Channel Outreach',
                'timestamp' => $cmTime,
                'title' => "{$channelLabel} Outreach: " . ucwords(str_replace('_', ' ', (string)($cm->template ?: 'Direct Message'))),
                'details' => "Content: \"" . ($cm->message ?: 'Communication dispatched.') . "\" [Delivery Status: " . ucfirst((string)$cm->status) . "]",
                'icon' => 'chat-bubble-bottom-center-text',
                'color' => 'bg-sky-100 text-sky-950 border-sky-300',
                'badge' => "{$channelLabel} (" . ucfirst((string)$cm->status) . ")",
                'actor' => 'Communication Hub',
            ]);
        }

        // 8. Outbound Calls
        $calls = LeadCall::where('lead_id', $lead->id)->get();
        foreach ($calls as $call) {
            $callTime = $call->called_at ? Carbon::parse($call->called_at) : Carbon::parse($call->created_at);
            $events->push([
                'id' => 'call_' . $call->id,
                'type' => 'call',
                'category' => 'Voice Outreach',
                'timestamp' => $callTime,
                'title' => "Call Logged: " . ($call->outcome ?: 'Connected'),
                'details' => "Duration: " . round(((int)$call->duration_seconds) / 60, 1) . " minutes. Outcome: " . ($call->outcome ?: 'Completed') . ".",
                'icon' => 'phone',
                'color' => 'bg-emerald-100 text-emerald-950 border-emerald-300',
                'badge' => 'Call (' . ($call->outcome ?: 'Connected') . ')',
                'actor' => 'Sales Executive',
            ]);
        }

        // 9. Site Visits & Meetings
        $visits = SiteVisit::where('lead_id', $lead->id)->get();
        foreach ($visits as $sv) {
            $vTime = $sv->visit_date ? Carbon::parse($sv->visit_date) : Carbon::parse($sv->created_at);
            $events->push([
                'id' => 'site_visit_' . $sv->id,
                'type' => 'site_visit',
                'category' => 'Field Engagement',
                'timestamp' => $vTime,
                'title' => "Site Visit: " . ucfirst((string)($sv->attendance ?: 'Scheduled')),
                'details' => "Outcome: " . ($sv->outcome ?: 'Property walkthrough conducted') . ".",
                'icon' => 'building-office',
                'color' => 'bg-teal-100 text-teal-950 border-teal-300',
                'badge' => 'Site Visit',
                'actor' => 'Field Executive',
            ]);
        }

        // Sort chronologically ascending for a forward journey (or descending as required)
        $sortedEvents = $events->sortByDesc('timestamp')->values()->all();

        return [
            'lead' => $lead,
            'summary' => [
                'lead_code' => $lead->lead_code,
                'name' => $lead->name,
                'project_name' => $lead->projectName,
                'current_stage' => $lead->current_stage,
                'assigned_to' => $lead->assignedTo?->name ?: 'Unassigned',
                'lead_cost' => $lead->lead_cost ? number_format((float)$lead->lead_cost, 2) . ' Credits' : '10.00 Credits (Default)',
                'sla_badge' => $lead->slaBadge,
                'total_events' => count($sortedEvents),
            ],
            'events' => $sortedEvents,
        ];
    }
}
