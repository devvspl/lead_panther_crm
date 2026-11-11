<?php

namespace App\Livewire\Activity;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Followup;
use App\Models\AuditLog;
use App\Support\NavigationConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FollowupList extends Component
{
    public ?Lead $lead = null;

    // Next follow-up prompt state
    public ?int $completingFollowupId = null;

    public string $nextDueAt = '';

    public string $nextNote = '';

    public ?string $newStage = null;

    public function markDone(int $followupId): void
    {
        $this->completingFollowupId = $followupId;
        $this->nextDueAt = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->nextNote = '';
    }

    public function completeWithNextStep(): void
    {
        if (! $this->completingFollowupId) {
            return;
        }

        DB::transaction(function () {
            $this->validate(['nextDueAt' => 'nullable|date|after:now', 'nextNote' => 'nullable|string|max:500']);
            $followup = Followup::lockForUpdate()->findOrFail($this->completingFollowupId);
            if (Lead::usesSalesPanel()) {
                $lead = Lead::panelQuery()->find($followup->lead_id);
                abort_unless($lead, 403);
                abort_unless(NavigationConfig::getRoleKey(auth()->user()) === 'sales-executive', 403);
                Validator::make(['action' => 'completeFollowupAction', 'status' => $followup->status, 'newStage' => $this->newStage], ['action' => Rule::in($lead->permittedSalesActions()), 'status' => 'in:pending', 'newStage' => 'prohibited'])->validate();
                AuditLog::create(['user_id' => auth()->id(), 'action' => 'lead.followup_completed', 'subject_type' => Lead::class, 'subject_id' => $lead->id, 'from_value' => json_encode(['followup_id' => $followup->id, 'status' => $followup->status]), 'to_value' => json_encode(['status' => 'completed', 'next_due_at' => $this->nextDueAt, 'remarks' => $this->nextNote]), 'created_at' => now()]);
            }
        if ($followup) {
            $followup->update(['status' => 'completed']);
        }

        if ($this->nextDueAt) {
            Followup::create([
                'lead_id' => $followup->lead_id,
                'created_by' => auth()->id() ?? 1,
                'due_at' => $this->nextDueAt,
                'note' => $this->nextNote ?: 'Follow-up next step',
                'status' => 'pending',
            ]);
        }

        if ($this->newStage && $this->lead) {
            $this->lead->update(['current_stage' => $this->newStage, 'status' => $this->newStage]);
        }

        $this->completingFollowupId = null;
        $this->dispatch('toast', type: 'success', message: 'Follow-up marked completed and next step scheduled.');
        });
    }

    public function render()
    {
        $userId = auth()->id() ?? 1;

        $baseQuery = Followup::query()->with(['lead' => fn ($q) => $q->withoutGlobalScopes()->with(['project' => fn ($p) => $p->withoutGlobalScopes()])]);
        if (Lead::usesSalesPanel()) {
            $baseQuery->whereIn('lead_id', Lead::panelQuery()->select('id'));
        }

        // Scope to user's created follow-ups or follow-ups for leads assigned to this user
        if (! Lead::usesSalesPanel()) {
        $baseQuery->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhereHas('lead', fn($lq) => $lq->where('assigned_to', $userId));
        });
        }

        $myOverdue = (clone $baseQuery)
            ->where('status', 'pending')
            ->where('due_at', '<', now()->startOfDay())
            ->orderBy('due_at', 'asc')
            ->get();

        $myToday = (clone $baseQuery)
            ->where('status', 'pending')
            ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
            ->orderBy('due_at', 'asc')
            ->get();

        $myUpcoming = (clone $baseQuery)
            ->where('status', 'pending')
            ->where('due_at', '>', now()->endOfDay())
            ->orderBy('due_at', 'asc')
            ->get();

        if ($this->lead && Lead::usesSalesPanel()) {
            abort_unless(Lead::panelQuery()->whereKey($this->lead->id)->exists(), 403);
        }
        $leadFollowups = $this->lead 
            ? Followup::where('lead_id', $this->lead->id)->with('lead.project')->latest('due_at')->get() 
            : collect();

        $view = view('livewire.activity.followup-list', [
            'myOverdue' => $myOverdue,
            'myToday' => $myToday,
            'myUpcoming' => $myUpcoming,
            'leadFollowups' => $leadFollowups,
        ]);

        return $this->lead ? $view : $view->layout('layouts.app');
    }
}
