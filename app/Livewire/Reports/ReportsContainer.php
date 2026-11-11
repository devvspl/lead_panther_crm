<?php

// TODO: Move reporting queries to dedicated analytics database when scaling

namespace App\Livewire\Reports;

use Livewire\Component;

class ReportsContainer extends Component
{
    #[\Livewire\Attributes\Url(as: 'tab', history: true)]
    public string $activeTab = 'source';

    public function mount(): void
    {
        if (!in_array($this->activeTab,['source','sla','replacement','followup','revenue','executive'],true)) $this->activeTab='source';
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab,['source','sla','replacement','followup','revenue','executive'],true),422);
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('livewire.reports.reports-container')->layout('layouts.app');
    }
}
