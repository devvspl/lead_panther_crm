<?php

namespace App\Observers;

class LeadObserver
{
    public function created(\App\Models\Lead $lead): void
    {
        if ($lead->delivery_plan_id) app(\App\Services\LeadDistributionService::class)->captureClientDeliveryLead($lead);
        if ($lead->internal_processing_pending) app(\App\Services\LeadDistributionService::class)->startInternalProcessing($lead);
        $this->directAssignment($lead);
    }

    public function updated(\App\Models\Lead $lead): void
    {
        if ($lead->wasChanged('assigned_to') && !$lead->webAssignmentRecorded) $this->directAssignment($lead);
    }

    private function directAssignment(\App\Models\Lead $lead): void
    {
        if ($lead->webAssignmentRecorded) return;
        \App\Models\LeadAssignment::where('lead_id', $lead->id)->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
        if (!$lead->assigned_to) {
            \App\Models\Lead::withoutGlobalScopes()->whereKey($lead->id)->update(['executive_assigned_at' => null, 'executive_response_due_at' => null, 'executive_first_response_at' => null]);
            return;
        }
        \App\Models\LeadAssignment::create(['lead_id' => $lead->id, 'assigned_to' => $lead->assigned_to,
            'assigned_by' => auth()->id() ?? $lead->assigned_to, 'assigned_at' => now()]);
    }
    public function creating(\App\Models\Lead $lead): void
    {
        app(\App\Services\LeadDistributionService::class)->scheduleDelivery($lead);
    }
}
