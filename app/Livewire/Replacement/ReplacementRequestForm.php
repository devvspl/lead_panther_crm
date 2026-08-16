<?php

namespace App\Livewire\Replacement;

use Livewire\Component;
use App\Models\Lead;
use App\Models\ReplacementReason;
use App\Models\LeadReplacement;
use App\Models\AuditLog;
use Carbon\Carbon;

class ReplacementRequestForm extends Component
{
    public bool $isOpen = false;
    public ?int $leadId = null;
    public ?int $reasonId = null;
    public string $note = '';

    protected $listeners = ['open-replacement-form' => 'openForm'];

    public function openForm(int $id): void
    {
        $this->leadId = $id;
        $this->isOpen = true;
        $firstReason = ReplacementReason::first();
        if ($firstReason) {
            $this->reasonId = $firstReason->id;
        }
    }

    public function closeForm(): void
    {
        $this->isOpen = false;
        $this->note = '';
    }

    public function submitRequest(): void
    {
        $this->validate([
            'leadId' => 'required|exists:leads,id',
            'reasonId' => 'required|exists:replacement_reasons,id',
        ]);

        $lead = Lead::find($this->leadId);
        $reason = ReplacementReason::find($this->reasonId);

        if (!$lead || !$reason) return;

        // SLA met calculation: first_response_at diff created_at <= 30 minutes
        $slaMet = false;
        if ($lead->first_response_at) {
            $diffMins = Carbon::parse($lead->created_at)->diffInMinutes(Carbon::parse($lead->first_response_at));
            $slaMet = ($diffMins <= 30);
        }

        // Create initial replacement request
        $replacement = LeadReplacement::create([
            'lead_id' => $lead->id,
            'reason_id' => $reason->id,
            'requested_by' => auth()->id() ?? 1,
            'requested_at' => now(),
            'sla_met' => $slaMet,
            'status' => 'pending',
        ]);

        // Audit Log entry for replacement request
        AuditLog::create([
            'user_id' => auth()->id() ?? 1,
            'action' => 'replacement.requested',
            'subject_type' => LeadReplacement::class,
            'subject_id' => $replacement->id,
            'from_value' => null,
            'to_value' => json_encode(['reason' => $reason->label, 'sla_met' => $slaMet, 'note' => $this->note]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
            'created_at' => now(),
        ]);

        // Auto-eligibility Evaluation Chain
        // 1. SLA check: (!requires_sla_check || slaMet)
        // 2. Reason eligibility: reason.is_eligible
        // 3. No prior approved replacement on this lead
        $canAutoApprove = (!$reason->requires_sla_check || $slaMet)
            && $reason->is_eligible
            && LeadReplacement::where('lead_id', $lead->id)->where('status', 'approved')->where('id', '!=', $replacement->id)->doesntExist();

        if ($canAutoApprove) {
            $replacement->approve(auth()->id() ?? 1);
            $this->dispatch('toast', type: 'success', message: 'Replacement Auto-Approved! Replacement lead created and credit refunded.');
        } else {
            $this->dispatch('toast', type: 'success', message: 'Replacement claim submitted for Account Manager review.');
        }

        $this->dispatch('lead-updated');
        $this->closeForm();
    }

    public function render()
    {
        $reasons = ReplacementReason::all();
        $lead = $this->leadId ? Lead::find($this->leadId) : null;

        return view('livewire.replacement.replacement-request-form', [
            'reasons' => $reasons,
            'lead' => $lead,
        ]);
    }
}
