<?php

namespace App\Livewire\Activity;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Followup;

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
        if (!$this->completingFollowupId) return;

        $followup = Followup::find($this->completingFollowupId);
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
    }

    public function render()
    {
        $userId = auth()->id() ?? 1;

        $baseQuery = Followup::query()->with(['lead.project']);

        // Scope to user's created follow-ups or follow-ups for leads assigned to this user
        $baseQuery->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhereHas('lead', fn($lq) => $lq->where('assigned_to', $userId));
        });

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
