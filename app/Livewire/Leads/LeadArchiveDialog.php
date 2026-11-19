<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use App\Services\LeadArchiveService;
use Livewire\Attributes\{Locked, On};
use Livewire\Component;

class LeadArchiveDialog extends Component
{
    #[Locked] public ?int $leadId = null;
    public string $reason = '';
    public bool $confirmed = false;

    public function boot(): void { LeadArchiveService::authorize(); }

    #[On('archive-lead')]
    public function open(int $id): void
    {
        LeadArchiveService::authorize();
        $this->leadId = Lead::withoutGlobalScopes()->findOrFail($id)->id;
        $this->reset('reason', 'confirmed');
        $this->resetValidation();
        $this->dispatch('open-modal', 'archive-lead-confirmation');
    }

    public function archive(LeadArchiveService $service): void
    {
        LeadArchiveService::authorize();
        $this->validate(['confirmed' => 'accepted', 'reason' => 'nullable|string|max:2000', 'leadId' => 'required|integer']);
        $service->archive($this->leadId, $this->reason);
        $this->leadId = null;
        $this->dispatch('close-modal', 'archive-lead-confirmation');
        $this->dispatch('lead-updated');
        $this->dispatch('lead-archived');
        $this->dispatch('toast', type: 'success', message: 'Lead archived.');
    }

    public function render() { return view('livewire.leads.lead-archive-dialog'); }
}
