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
            LeadReplacement::create([
                'lead_id' => $lead->id,
                'reason_id' => $this->replacementReasonId,
                'requested_by' => auth()->id() ?? 1,
                'requested_at' => now(),
                'status' => 'pending',
            ]);

            $oldStage = $lead->current_stage;
            $lead->update(['current_stage' => 'replaced', 'status' => 'replaced']);

            LeadStatusHistory::create([
                'lead_id' => $lead->id,
                'from_status' => $oldStage,
                'to_status' => 'replaced',
                'changed_by' => auth()->id() ?? 1,
                'changed_at' => now(),
            ]);

            $this->dispatch('toast', type: 'success', message: 'Replacement requested.');
            $this->dispatch('lead-updated');
        }
    }

    public function render()
    {
        $lead = $this->leadId ? Lead::with(['client', 'project', 'campaign', 'leadSource', 'assignedTo'])->find($this->leadId) : null;
        $communications = $this->leadId ? LeadCommunication::where('lead_id', $this->leadId)->latest()->get() : [];
        $statusHistory = $this->leadId ? LeadStatusHistory::where('lead_id', $this->leadId)->latest('changed_at')->get() : [];
        $replacementReasons = ReplacementReason::all();

        return view('livewire.leads.lead-detail', [
            'lead' => $lead,
            'communications' => $communications,
            'statusHistory' => $statusHistory,
            'replacementReasons' => $replacementReasons,
        ]);
    }
}
