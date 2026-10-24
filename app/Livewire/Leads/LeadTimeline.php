<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use App\Models\Lead;
use App\Services\LeadJourneyService;
use Livewire\Attributes\On;

class LeadTimeline extends Component
{
    public bool $isOpen = false;
    public ?int $leadId = null;
    public string $filterType = 'all'; // 'all' | 'status' | 'assignment' | 'credit' | 'sla' | 'replacement' | 'communication'
    public string $sortOrder = 'desc'; // 'desc' | 'asc'

    #[On('open-lead-journey')]
    #[On('show-lead-journey')]
    public function openJourney(int $id): void
    {
        $this->leadId = $id;
        $this->isOpen = true;
        $this->filterType = 'all';
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->leadId = null;
    }

    public function setFilter(string $filter): void
    {
        $this->filterType = $filter;
    }

    public function toggleSort(): void
    {
        $this->sortOrder = $this->sortOrder === 'desc' ? 'asc' : 'desc';
    }

    public function render(LeadJourneyService $journeyService)
    {
        $journey = null;
        if ($this->leadId) {
            $lead = Lead::with(['client', 'project', 'campaign', 'leadSource', 'assignedTo'])->find($this->leadId);
            if ($lead) {
                $journey = $journeyService->getJourney($lead);

                // Apply milestone filtering
                if ($this->filterType !== 'all') {
                    $journey['events'] = array_values(array_filter($journey['events'], function ($evt) {
                        return $evt['type'] === $this->filterType;
                    }));
                }

                // Apply sorting
                if ($this->sortOrder === 'asc') {
                    usort($journey['events'], fn($a, $b) => $a['timestamp']->timestamp <=> $b['timestamp']->timestamp);
                } else {
                    usort($journey['events'], fn($a, $b) => $b['timestamp']->timestamp <=> $a['timestamp']->timestamp);
                }
            }
        }

        return view('livewire.leads.lead-timeline', [
            'journey' => $journey,
        ]);
    }
}
