<?php

namespace App\Livewire\Activity;

use Livewire\Component;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\ProjectUnit;

class SiteVisitForm extends Component
{
    public Lead $lead;

    public ?int $projectUnitId = null;
    public string $visitedAt = '';
    public bool $attended = true;
    public string $outcome = 'interested';
    public string $remarks = '';

    // Stage suggestion confirmation toggle
    public bool $showStageSuggestModal = false;

    public function mount(Lead $lead): void
    {
        $this->lead = $lead;
        $this->visitedAt = now()->format('Y-m-d\TH:i');

        $firstUnit = ProjectUnit::where('project_id', $this->lead->project_id)->first();
        $this->projectUnitId = $firstUnit?->id;
    }

    public function logVisit(): void
    {
        $this->validate([
            'visitedAt' => 'required',
            'outcome' => 'required',
        ]);

        SiteVisit::create([
            'lead_id' => $this->lead->id,
            'project_unit_id' => $this->projectUnitId,
            'executive_id' => auth()->id() ?? 1,
            'visit_date' => $this->visitedAt,
            'attendance' => $this->attended ? 'attended' : 'no_show',
            'outcome' => $this->outcome,
            'remarks' => $this->remarks,
        ]);

        if (strtolower($this->lead->current_stage ?? '') !== 'site visit') {
            $this->showStageSuggestModal = true;
        } else {
            $this->dispatch('toast', type: 'success', message: 'Site visit logged successfully.');
        }
    }

    public function confirmUpdateStage(): void
    {
        $this->lead->update(['current_stage' => 'Site Visit', 'status' => 'site_visit']);
        $this->showStageSuggestModal = false;
        $this->dispatch('toast', type: 'success', message: 'Site visit logged & lead stage updated to Site Visit.');
    }

    public function render()
    {
        $units = ProjectUnit::where('project_id', $this->lead->project_id)->get();
        if ($units->isEmpty()) {
            $units = ProjectUnit::all();
        }

        return view('livewire.activity.site-visit-form', [
            'units' => $units,
        ]);
    }
}
