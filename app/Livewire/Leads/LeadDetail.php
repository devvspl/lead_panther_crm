<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use App\Models\Lead;
use App\Models\LeadCommunication;
use App\Models\LeadCall;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Negotiation;
use App\Models\Booking;
use App\Models\PaymentReceived;
use App\Models\Followup;
use App\Models\LeadReplacement;
use App\Models\ReplacementReason;
use App\Models\LeadStatusHistory;
use App\Models\LeadAssignment;
use App\Models\AuditLog;
use Carbon\Carbon;
use Livewire\Attributes\On;

class LeadDetail extends Component
{
    public bool $isOpen = false;
    public ?int $leadId = null;
    public string $activeTab = 'stage_details'; // stage_details, timeline, communications

    // Stage-specific form inputs
    public string $actionRemarks = '';
    public string $targetNextStage = '';

    // Contact / Call Form
    public string $callOutcome = 'Connected';
    public int $callDuration = 5;
    public string $callNotes = '';

    // Follow-up Form
    public string $followupDueAt = '';
    public string $followupNote = '';
    public ?int $completingFollowupId = null;
    public string $followupCompleteOutcome = 'Completed';

    // Meeting Form
    public string $meetingScheduledAt = '';
    public string $meetingLocation = 'Site Office';
    public string $meetingStatus = 'Scheduled';
    public string $meetingNotes = '';

    // Site Visit Form
    public string $visitDate = '';
    public string $visitAttendance = 'attended';
    public string $visitOutcome = 'Liked layout';
    public string $visitRemarks = '';

    // Negotiation Form
    public string $quotedAmount = '';
    public string $negotiationNotes = '';

    // Booking Form
    public string $bookingUnit = '';
    public string $bookingAmount = '';
    public string $bookingDate = '';

    // Payment Form
    public string $paymentAmount = '';
    public string $paymentMethod = 'Bank Transfer';
    public string $paymentTransactionId = '';
    public string $paymentNotes = '';

    // Lost / Invalid Form
    public string $lostReason = 'Budget mismatch';
    public string $lostRemarks = '';

    // Replacement Form
    public ?int $replacementReasonId = null;
    public string $replacementRemarks = '';

    #[On('open-lead-detail')]
    public function openLead(int $id): void
    {
        $user = auth()->user();
        $lead = Lead::with(['client', 'project', 'campaign', 'leadSource', 'assignedTo', 'siteVisits', 'followups', 'meetings', 'bookings.payments'])->find($id);

        if (!$lead) {
            $this->dispatch('toast', type: 'error', message: 'Lead not found.');
            return;
        }

        // Backend authorization validation
        if (!$this->hasLeadAccess($lead, $user)) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized: You do not have permission to access this lead.');
            return;
        }

        $this->leadId = $id;
        $this->isOpen = true;
        $this->activeTab = 'stage_details';

        // Prepopulate default form dates and fields
        $this->followupDueAt = now()->addDays(1)->format('Y-m-d\TH:i');
        $this->followupNote = '';
        $this->meetingScheduledAt = now()->addDays(1)->format('Y-m-d\TH:i');
        $this->meetingLocation = 'Site Office';
        $this->visitDate = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->bookingDate = now()->format('Y-m-d');
        $this->actionRemarks = '';
        $this->targetNextStage = '';

        // Prepopulate existing stage data if present
        $latestVisit = $lead->siteVisits()->latest()->first();
        if ($latestVisit) {
            $this->visitAttendance = $latestVisit->attendance ?: 'attended';
            $this->visitOutcome = $latestVisit->outcome ?: 'Liked layout';
            $this->visitRemarks = $latestVisit->remarks ?: '';
        }

        $latestMeeting = $lead->meetings()->latest()->first();
        if ($latestMeeting) {
            $this->meetingLocation = $latestMeeting->location ?: 'Site Office';
            $this->meetingStatus = $latestMeeting->outcome ?: 'Scheduled';
            $this->meetingNotes = $latestMeeting->notes ?: '';
        }

        $latestBooking = $lead->bookings()->latest()->first();
        if ($latestBooking) {
            $this->bookingAmount = (string) $latestBooking->booking_amount;
            $this->paymentAmount = (string) $latestBooking->booking_amount;
        }

        $firstReason = ReplacementReason::first();
        $this->replacementReasonId = $firstReason ? $firstReason->id : null;
        $this->replacementRemarks = '';
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->leadId = null;
    }

    /**
     * Strict role & organization access check
     */
    protected function hasLeadAccess(Lead $lead, $user): bool
    {
        if (!$user) return false;

        $roleKey = \App\Support\NavigationConfig::getRoleKey($user);

        if ($roleKey === 'super-admin' || $user->hasRole('Super Admin') || $user->hasRole('super-admin')) {
            return true;
        }

        if ($roleKey === 'sales-executive' || $user->hasRole('sales-executive') || $user->hasRole('Sales Executive')) {
            return (int) $lead->assigned_to === (int) $user->id;
        }

        if ($roleKey === 'builder' || $user->hasRole('builder') || $user->hasRole('Builder')) {
            $orgId = $user->organization_id;
            if (!$orgId) {
                return (int) $lead->client_id === (int) $user->id || (int) $lead->assigned_to === (int) $user->id;
            }

            return ($lead->client && (int) $lead->client->organization_id === (int) $orgId)
                || ($lead->assignedTo && (int) $lead->assignedTo->organization_id === (int) $orgId)
                || (int) $lead->client_id === (int) $user->id
                || (int) $lead->assigned_to === (int) $user->id;
        }

        return true;
    }

    protected function getActiveLead(): ?Lead
    {
        if (!$this->leadId) return null;
        $lead = Lead::find($this->leadId);
        if (!$lead || !$this->hasLeadAccess($lead, auth()->user())) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized action on lead.');
            return null;
        }
        return $lead;
    }

    /**
     * Unified state transition recorder with validation and audit logging
     */
    protected function recordStageTransition(Lead $lead, string $newStage, string $remarks = ''): void
    {
        $oldStage = $lead->current_stage;
        $user = auth()->user();

        $updateData = [
            'current_stage' => $newStage,
            'status' => in_array($newStage, ['closed_won']) ? 'closed_won' : (in_array($newStage, ['lost', 'invalid', 'unreachable']) ? 'closed' : 'in_progress'),
        ];

        if (!$lead->first_response_at) {
            $updateData['first_response_at'] = now();
        }

        $lead->update($updateData);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => $oldStage,
            'to_status' => $newStage,
            'changed_by' => $user?->id ?? 1,
            'changed_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $user?->id ?? 1,
            'action' => 'lead.stage_changed',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'from_value' => json_encode(['current_stage' => $oldStage]),
            'to_value' => json_encode([
                'current_stage' => $newStage,
                'remarks' => $remarks,
            ]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
            'created_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // STAGE-SPECIFIC ACTIONS
    // -------------------------------------------------------------------------

    // Stage 1: New / Contacted
    public function logCallAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'callOutcome' => 'required|string|max:100',
            'callDuration' => 'required|integer|min:0',
        ]);

        $user = auth()->user();

        LeadCall::create([
            'lead_id' => $lead->id,
            'user_id' => $user?->id ?? 1,
            'duration_seconds' => $this->callDuration * 60,
            'outcome' => $this->callOutcome,
            'called_at' => now(),
        ]);

        if (!$lead->first_response_at) {
            $lead->update(['first_response_at' => now()]);
        }

        if (in_array($lead->current_stage, ['new', 'assigned'])) {
            $this->recordStageTransition($lead, 'contacted', "Call logged: {$this->callOutcome}");
        } else {
            AuditLog::create([
                'user_id' => $user?->id ?? 1,
                'action' => 'lead.call_logged',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'from_value' => json_encode([]),
                'to_value' => json_encode(['outcome' => $this->callOutcome, 'duration_minutes' => $this->callDuration, 'notes' => $this->callNotes]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
                'created_at' => now(),
            ]);
        }

        $this->dispatch('toast', type: 'success', message: 'Call log recorded successfully.');
        $this->dispatch('lead-updated');
    }

    public function addRemarksAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'actionRemarks' => 'required|string|min:3|max:1000',
        ]);

        $user = auth()->user();

        AuditLog::create([
            'user_id' => $user?->id ?? 1,
            'action' => 'lead.note_added',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'from_value' => json_encode([]),
            'to_value' => json_encode(['note' => $this->actionRemarks]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
            'created_at' => now(),
        ]);

        $this->actionRemarks = '';
        $this->dispatch('toast', type: 'success', message: 'Remarks saved to activity history.');
        $this->dispatch('lead-updated');
    }

    // Stage 2: Interested -> Schedule Follow-up or Advance
    public function markInterestedAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->recordStageTransition($lead, 'interested', $this->actionRemarks ?: 'Customer confirmed interest in project.');
        $this->actionRemarks = '';
        $this->dispatch('toast', type: 'success', message: 'Lead marked as Interested.');
        $this->dispatch('lead-updated');
    }

    public function scheduleFollowupAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'followupDueAt' => 'required|date',
            'followupNote' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $user?->id ?? 1,
            'due_at' => Carbon::parse($this->followupDueAt),
            'note' => $this->followupNote ?: 'Customer follow-up call',
            'status' => 'pending',
        ]);

        if (in_array($lead->current_stage, ['new', 'contacted', 'interested'])) {
            $this->recordStageTransition($lead, 'follow_up', "Follow-up scheduled for {$this->followupDueAt}");
        }

        $this->followupNote = '';
        $this->dispatch('toast', type: 'success', message: 'Follow-up scheduled successfully.');
        $this->dispatch('lead-updated');
    }

    // Stage 3: Follow-Up & Reminder
    public function completeFollowupAction(int $followupId): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $fu = Followup::where('lead_id', $lead->id)->find($followupId);
        if ($fu) {
            $fu->update(['status' => 'completed']);
            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'lead.followup_completed',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'from_value' => json_encode(['status' => 'pending']),
                'to_value' => json_encode(['status' => 'completed', 'outcome' => $this->followupCompleteOutcome]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
                'created_at' => now(),
            ]);
            $this->dispatch('toast', type: 'success', message: 'Follow-up marked completed.');
            $this->dispatch('lead-updated');
        }
    }

    public function rescheduleFollowupAction(int $followupId): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'followupDueAt' => 'required|date',
        ]);

        $fu = Followup::where('lead_id', $lead->id)->find($followupId);
        if ($fu) {
            $oldDue = $fu->due_at;
            $fu->update([
                'due_at' => Carbon::parse($this->followupDueAt),
                'note' => $this->followupNote ?: $fu->note,
                'status' => 'pending',
            ]);

            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'lead.followup_rescheduled',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'from_value' => json_encode(['due_at' => $oldDue]),
                'to_value' => json_encode(['due_at' => $this->followupDueAt, 'note' => $this->followupNote]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
                'created_at' => now(),
            ]);

            $this->dispatch('toast', type: 'success', message: 'Follow-up rescheduled.');
            $this->dispatch('lead-updated');
        }
    }

    // Stage 4: Meeting Scheduled
    public function scheduleMeetingAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'meetingScheduledAt' => 'required|date',
            'meetingLocation' => 'required|string|max:200',
        ]);

        $user = auth()->user();

        Meeting::create([
            'lead_id' => $lead->id,
            'scheduled_at' => Carbon::parse($this->meetingScheduledAt),
            'location' => $this->meetingLocation,
            'type' => 'In-Person Meeting',
            'notes' => $this->meetingNotes ?: 'Meeting scheduled with customer',
            'outcome' => 'Scheduled',
        ]);

        $this->recordStageTransition($lead, 'meeting_scheduled', "Meeting scheduled at {$this->meetingLocation} for {$this->meetingScheduledAt}");
        $this->dispatch('toast', type: 'success', message: 'Meeting scheduled successfully.');
        $this->dispatch('lead-updated');
    }

    public function updateMeetingStatusAction(string $status): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $latestMeeting = $lead->meetings()->latest()->first();
        if ($latestMeeting) {
            $latestMeeting->update([
                'outcome' => $status,
                'notes' => $this->meetingNotes ?: $latestMeeting->notes,
            ]);
        }

        AuditLog::create([
            'user_id' => auth()->id() ?? 1,
            'action' => 'lead.meeting_updated',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'from_value' => json_encode([]),
            'to_value' => json_encode(['outcome' => $status, 'notes' => $this->meetingNotes]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
            'created_at' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: "Meeting marked as {$status}.");
        $this->dispatch('lead-updated');
    }

    // Stage 5: Site Visit Completed
    public function saveSiteVisitAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'visitDate' => 'required|date',
            'visitAttendance' => 'required|string',
            'visitOutcome' => 'required|string|max:200',
        ]);

        $user = auth()->user();

        SiteVisit::create([
            'lead_id' => $lead->id,
            'visit_date' => Carbon::parse($this->visitDate),
            'executive_id' => $user?->id ?? 1,
            'attendance' => $this->visitAttendance,
            'outcome' => $this->visitOutcome,
            'remarks' => $this->visitRemarks,
        ]);

        $this->recordStageTransition($lead, 'site_visit', "Site visit recorded: {$this->visitOutcome}");
        $this->dispatch('toast', type: 'success', message: 'Site visit recorded successfully.');
        $this->dispatch('lead-updated');
    }

    // Stage 6: Negotiation & Proposal
    public function saveNegotiationAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'negotiationNotes' => 'required|string|max:1000',
        ]);

        $user = auth()->user();

        Negotiation::create([
            'lead_id' => $lead->id,
            'notes' => $this->negotiationNotes,
            'status' => 'in_progress',
        ]);

        if ($this->quotedAmount) {
            $numericBudget = (float) str_replace([',', ' '], '', $this->quotedAmount);
            if ($numericBudget > 0) {
                $lead->update(['budget' => $numericBudget]);
            }
        }

        $this->recordStageTransition($lead, 'negotiation', "Negotiation note: {$this->negotiationNotes}");
        $this->dispatch('toast', type: 'success', message: 'Negotiation notes recorded.');
        $this->dispatch('lead-updated');
    }

    // Stage 7: Unit Booking
    public function saveBookingAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'bookingAmount' => 'required|numeric|min:1',
            'bookingUnit' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();

        $unit = null;
        if ($lead->project_id) {
            $unit = \App\Models\ProjectUnit::firstOrCreate(
                ['project_id' => $lead->project_id, 'unit_number' => $this->bookingUnit ?: 'Unit-101'],
                ['type' => 'Standard', 'price' => (float) $this->bookingAmount, 'status' => 'booked']
            );
        } else {
            $proj = \App\Models\Project::first();
            if ($proj) {
                $unit = \App\Models\ProjectUnit::firstOrCreate(
                    ['project_id' => $proj->id, 'unit_number' => $this->bookingUnit ?: 'Unit-101'],
                    ['type' => 'Standard', 'price' => (float) $this->bookingAmount, 'status' => 'booked']
                );
            }
        }

        $booking = Booking::create([
            'lead_id' => $lead->id,
            'project_unit_id' => $unit?->id ?? 1,
            'booking_amount' => (float) $this->bookingAmount,
            'booked_at' => $this->bookingDate ? Carbon::parse($this->bookingDate) : now(),
            'status' => 'confirmed',
        ]);

        $lead->update(['booking_status' => 'confirmed']);

        $this->recordStageTransition($lead, 'booking', "Unit booking created for ₹".number_format((float) $this->bookingAmount));
        $this->dispatch('toast', type: 'success', message: 'Unit booking recorded successfully.');
        $this->dispatch('lead-updated');
    }

    // Stage 8: Payment Received
    public function recordPaymentAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'paymentAmount' => 'required|numeric|min:1',
            'paymentMethod' => 'required|string|max:50',
            'paymentTransactionId' => 'nullable|string|max:100',
        ]);

        $latestBooking = $lead->bookings()->latest()->first();

        PaymentReceived::create([
            'booking_id' => $latestBooking?->id,
            'amount' => (float) $this->paymentAmount,
            'method' => $this->paymentMethod,
            'transaction_id' => $this->paymentTransactionId ?: 'TXN-'.strtoupper(uniqid()),
            'paid_at' => now(),
            'status' => 'completed',
        ]);

        $this->recordStageTransition($lead, 'payment_received', "Payment of ₹".number_format((float) $this->paymentAmount)." recorded via {$this->paymentMethod}");
        $this->dispatch('toast', type: 'success', message: 'Payment recorded successfully.');
        $this->dispatch('lead-updated');
    }

    // Stage 9: Closed Won
    public function markClosedWonAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->recordStageTransition($lead, 'closed_won', $this->actionRemarks ?: 'Deal successfully closed won.');
        $this->dispatch('toast', type: 'success', message: 'Congratulations! Lead marked as Closed Won.');
        $this->dispatch('lead-updated');
    }

    // Stage 10: Lost (Mandatory Reason & Remarks)
    public function markLostAction(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'lostReason' => 'required|string|min:3|max:100',
            'lostRemarks' => 'required|string|min:5|max:1000',
        ]);

        $this->recordStageTransition($lead, 'lost', "Reason: {$this->lostReason}. Remarks: {$this->lostRemarks}");
        $this->dispatch('toast', type: 'info', message: 'Lead marked as Lost.');
        $this->dispatch('lead-updated');
    }

    // Outcome: Invalid / Unreachable
    public function markInvalidAction(string $statusType = 'invalid'): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $this->validate([
            'lostRemarks' => 'required|string|min:5|max:1000',
        ]);

        $target = in_array($statusType, ['unreachable', 'wrong_number']) ? 'unreachable' : 'invalid';
        $this->recordStageTransition($lead, $target, "Marked {$target}. Remarks: {$this->lostRemarks}");
        $this->dispatch('toast', type: 'warning', message: "Lead marked as {$target}.");
        $this->dispatch('lead-updated');
    }

    // Super Admin Replacement Request (Builder / Developer only)
    public function requestReplacement(): void
    {
        $lead = $this->getActiveLead();
        if (!$lead) return;

        $user = auth()->user();
        $isSales = \App\Support\NavigationConfig::getRoleKey($user) === 'sales-executive' || $user->hasRole(['sales-executive', 'Sales Executive']);
        if ($isSales) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized: Sales Executives cannot submit replacement claims.');
            return;
        }

        $this->validate([
            'replacementReasonId' => 'required|exists:replacement_reasons,id',
            'replacementRemarks' => 'nullable|string|max:1000',
        ]);

        if ($lead->replacement_status === 'pending') {
            $this->dispatch('toast', type: 'warning', message: 'A replacement claim is already pending Super Admin review for this lead.');
            return;
        }

        $reason = ReplacementReason::find($this->replacementReasonId);

        $slaMet = true;
        if ($lead->created_at) {
            $slaMet = Carbon::parse($lead->created_at)->diffInMinutes(now()) <= 30;
        }

        $replacement = LeadReplacement::create([
            'lead_id' => $lead->id,
            'reason_id' => $reason->id,
            'requested_by' => $user?->id ?? 1,
            'requested_at' => now(),
            'sla_met' => $slaMet,
            'status' => 'pending',
        ]);

        $lead->update(['replacement_status' => 'pending']);

        AuditLog::create([
            'user_id' => $user?->id ?? 1,
            'action' => 'lead.return_requested',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'from_value' => json_encode(['replacement_status' => 'none']),
            'to_value' => json_encode([
                'replacement_status' => 'pending',
                'replacement_id' => $replacement->id,
                'reason_id' => $reason->id,
                'reason_label' => $reason->label,
                'remarks' => $this->replacementRemarks,
                'sla_met' => $slaMet,
            ]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
            'created_at' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Lead returned to Super Admin for replacement review. Credits will be refunded upon approval.');
        $this->dispatch('lead-updated');
    }

    public function showFullJourney(): void
    {
        if ($this->leadId) {
            $this->dispatch('show-lead-journey', id: $this->leadId);
        }
    }

    public function render(\App\Services\LeadJourneyService $journeyService)
    {
        $lead = null;
        $communications = [];
        $statusHistory = [];
        $replacementReasons = [];
        $journey = null;
        $leadFollowups = [];
        $leadCalls = [];
        $latestMeeting = null;
        $latestSiteVisit = null;
        $latestBooking = null;
        $latestNegotiation = null;
        $latestReplacement = null;
        $assignmentHistory = [];
        $isBuilder = false;
        $isSalesExecutive = false;

        if ($this->leadId) {
            $user = auth()->user();
            $roleKey = \App\Support\NavigationConfig::getRoleKey($user);
            $isBuilder = $roleKey === 'builder' || ($user && $user->hasRole(['builder', 'Builder', 'Builder/Developer']));
            $isSalesExecutive = $roleKey === 'sales-executive' || ($user && $user->hasRole(['sales-executive', 'Sales Executive']));

            $lead = Lead::with([
                'client.organization',
                'project',
                'campaign',
                'leadSource',
                'assignedTo',
                'metadata',
                'followups',
                'siteVisits',
                'meetings',
                'bookings.payments',
                'replacements.reason',
            ])->find($this->leadId);

            if ($lead) {
                $communications = LeadCommunication::where('lead_id', $this->leadId)->latest()->get();
                $statusHistory = LeadStatusHistory::where('lead_id', $this->leadId)->with('changer')->latest('changed_at')->get();
                $leadFollowups = Followup::where('lead_id', $this->leadId)->orderBy('due_at', 'desc')->get();
                $leadCalls = LeadCall::where('lead_id', $this->leadId)->latest('called_at')->get();
                $latestMeeting = $lead->meetings()->latest()->first();
                $latestSiteVisit = $lead->siteVisits()->latest()->first();
                $latestBooking = $lead->bookings()->with('payments')->latest()->first();
                $latestNegotiation = Negotiation::where('lead_id', $this->leadId)->latest()->first();
                $latestReplacement = $lead->replacements()->latest()->first();
                $assignmentHistory = LeadAssignment::where('lead_id', $this->leadId)->with(['assignee', 'assigner'])->latest('assigned_at')->get();
                $replacementReasons = ReplacementReason::all();
                $journey = $journeyService->getJourney($lead);
            }
        }

        return view('livewire.leads.lead-detail', [
            'lead' => $lead,
            'communications' => $communications,
            'statusHistory' => $statusHistory,
            'leadFollowups' => $leadFollowups,
            'leadCalls' => $leadCalls,
            'latestMeeting' => $latestMeeting,
            'latestSiteVisit' => $latestSiteVisit,
            'latestBooking' => $latestBooking,
            'latestNegotiation' => $latestNegotiation,
            'latestReplacement' => $latestReplacement,
            'assignmentHistory' => $assignmentHistory,
            'replacementReasons' => $replacementReasons,
            'journey' => $journey,
            'isBuilder' => $isBuilder,
            'isSalesExecutive' => $isSalesExecutive,
        ]);
    }
}
