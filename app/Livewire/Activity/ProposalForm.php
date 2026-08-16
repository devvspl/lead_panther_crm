<?php

namespace App\Livewire\Activity;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\ProjectUnit;
use Illuminate\Support\Facades\URL;

class ProposalForm extends Component
{
    public Lead $lead;

    public ?int $projectUnitId = null;
    public float $price = 7500000.00;
    public float $discount = 50000.00;
    public string $validUntil = '';
    public string $terms = '10% Booking Token, 40% Slab Completion, 50% Possession Handover.';

    public ?string $generatedSignedUrl = null;

    public function mount(Lead $lead): void
    {
        $this->lead = $lead;
        $this->validUntil = now()->addDays(15)->format('Y-m-d');

        $firstUnit = ProjectUnit::where('project_id', $this->lead->project_id)->first();
        $this->projectUnitId = $firstUnit?->id;
    }

    public function createProposal(): void
    {
        $this->validate([
            'price' => 'required|numeric|min:1',
            'validUntil' => 'required',
        ]);

        $proposal = Proposal::create([
            'lead_id' => $this->lead->id,
            'project_unit_id' => $this->projectUnitId,
            'price' => $this->price,
            'discount' => $this->discount,
            'validity_date' => $this->validUntil,
            'terms' => $this->terms,
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        $this->lead->update(['current_stage' => 'Proposal', 'status' => 'proposal']);

        // Generate temporary signed URL valid for 30 days
        $this->generatedSignedUrl = URL::temporarySignedRoute(
            'proposals.public_view',
            now()->addDays(30),
            ['proposal' => $proposal->id]
        );

        $this->dispatch('toast', type: 'success', message: 'Commercial proposal created & shareable signed URL generated.');
    }

    public function render()
    {
        $units = ProjectUnit::where('project_id', $this->lead->project_id)->get();
        if ($units->isEmpty()) {
            $units = ProjectUnit::all();
        }

        $leadProposals = Proposal::where('lead_id', $this->lead->id)->latest('id')->get();

        return view('livewire.activity.proposal-form', [
            'units' => $units,
            'leadProposals' => $leadProposals,
        ]);
    }
}
