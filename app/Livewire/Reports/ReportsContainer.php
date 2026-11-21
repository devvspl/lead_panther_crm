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
        if ($this->activeTab === 'revenue' && !\App\Support\FinancialVisibility::allowed()) $this->activeTab = 'sla';
        if ($this->activeTab === 'source' && ! \App\Support\LeadSourceVisibility::allowed()) $this->activeTab = 'sla';
        if (!in_array($this->activeTab,['source','sla','replacement','followup','revenue','executive'],true)) $this->activeTab='source';
    }

    public function setTab(string $tab): void
    {
        abort_if($tab === 'revenue' && !\App\Support\FinancialVisibility::allowed(), 403);
        abort_if($tab === 'source' && ! \App\Support\LeadSourceVisibility::allowed(), 403);
        abort_unless(in_array($tab,['source','sla','replacement','followup','revenue','executive'],true),422);
        $this->activeTab = $tab;
    }

    public function render()
    {
        if ($this->activeTab === 'revenue' && !\App\Support\FinancialVisibility::allowed()) $this->activeTab = 'sla';
        if ($this->activeTab === 'source' && ! \App\Support\LeadSourceVisibility::allowed()) $this->activeTab = 'sla';
        if (\App\Support\TeamAccess::isLeader(auth()->user())) abort_unless(auth()->user()->can('view-team-reports'),403);
        return view('livewire.reports.reports-container')->layout('layouts.app');
    }
}
