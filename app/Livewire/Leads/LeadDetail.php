<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use App\Models\Lead;
use App\Models\LeadCommunication;
use App\Models\LeadCall;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Proposal;
use App\Models\LeadReplacement;
use App\Models\ReplacementReason;
use App\Models\LeadStatusHistory;
use Livewire\Attributes\On;

class LeadDetail extends Component
{
    public bool $isOpen = false;
    public ?int $leadId = null;
    public string $activeTab = 'timeline'; // timeline, communications, activities

    // Form inputs for quick actions
    public string $callOutcome = 'Connected';
    public int $callDuration = 5;

    public string $meetingScheduledAt = '';
    public string $meetingLocation = '';

    public string $visitDate = '';
    public string $visitAttendance = 'attended';
    public string $visitOutcome = 'Liked layout';

    public ?int $replacementReasonId = null;

    #[On('open-lead-detail')]
    public function openLead(int $id): void
    {
        $this->leadId = $id;
        $this->isOpen = true;
        $this->meetingScheduledAt = now()->addDays(1)->format('Y-m-d\TH:i');
        $this->visitDate = now()->addDays(2)->format('Y-m-d\TH:i');
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function logCall(): void
    {
        if (!$this->leadId) return;

        LeadCall::create([
            'lead_id' => $this->leadId,
            'user_id' => auth()->id() ?? 1,
            'duration_seconds' => $this->callDuration * 60,
            'outcome' => $this->callOutcome,
            'called_at' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Call logged successfully.');
        $this->dispatch('lead-updated');
    }

    public function scheduleMeeting(): void
    {
        if (!$this->leadId) return;

        Meeting::create([
            'lead_id' => $this->leadId,
            'scheduled_at' => $this->meetingScheduledAt ? Carbon\Carbon::parse($this->meetingScheduledAt) : now()->addDay(),
            'location' => $this->meetingLocation ?: 'Site Office',
            'type' => 'In-Person Meeting',
            'notes' => 'Scheduled via Kanban Quick Action',
        ]);

        $this->dispatch('toast', type: 'success', message: 'Meeting scheduled.');
        $this->dispatch('lead-updated');
    }

    public function markSiteVisit(): void
    {
        if (!$this->leadId) return;

        SiteVisit::create([
            'lead_id' => $this->leadId,
            'visit_date' => $this->visitDate ? Carbon\Carbon::parse($this->visitDate) : now(),
            'executive_id' => auth()->id() ?? 1,
            'attendance' => $this->visitAttendance,
            'outcome' => $this->visitOutcome,
        ]);

        $this->dispatch('toast', type: 'success', message: 'Site visit recorded.');
        $this->dispatch('lead-updated');
    }

    public function requestReplacement(): void
    {
        if (!$this->leadId || !$this->replacementReasonId) return;

        $lead = Lead::find($this->leadId);
        if ($lead) {
            if ($lead->replacement_status === 'pending') {
                $this->dispatch('toast', type: 'warning', message: 'A replacement claim is already pending Super Admin review for this lead.');
                return;
            }

            LeadReplacement::create([
                'lead_id' => $lead->id,
                'reason_id' => $this->replacementReasonId,
                'requested_by' => auth()->id() ?? 1,
                'requested_at' => now(),
                'status' => 'pending',
                'sla_met' => $lead->created_at ? $lead->created_at->diffInMinutes(now()) <= 30 : true,
            ]);

            $lead->update(['replacement_status' => 'pending']);

            \App\Models\AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'lead.return_requested',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'from_value' => json_encode(['replacement_status' => 'none']),
                'to_value' => json_encode(['replacement_status' => 'pending', 'reason_id' => $this->replacementReasonId]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
                'created_at' => now(),
            ]);

            $this->dispatch('toast', type: 'success', message: 'Lead returned to Super Admin for replacement review. Credits will be refunded upon approval.');
            $this->dispatch('lead-updated');
        }
    }

    public function showFullJourney(): void
    {
        if ($this->leadId) {
            $this->dispatch('show-lead-journey', id: $this->leadId);
        }
    }

    public function render(\App\Services\LeadJourneyService $journeyService)
    {
        $lead = $this->leadId ? Lead::with(['client', 'project', 'campaign', 'leadSource', 'assignedTo', 'metadata'])->find($this->leadId) : null;
        $communications = $this->leadId ? LeadCommunication::where('lead_id', $this->leadId)->latest()->get() : [];
        $statusHistory = $this->leadId ? LeadStatusHistory::where('lead_id', $this->leadId)->latest('changed_at')->get() : [];
        $replacementReasons = ReplacementReason::all();
        $journey = $lead ? $journeyService->getJourney($lead) : null;

        return view('livewire.leads.lead-detail', [
            'lead' => $lead,
            'communications' => $communications,
            'statusHistory' => $statusHistory,
            'replacementReasons' => $replacementReasons,
            'journey' => $journey,
        ]);
    }
}
