<?php

// TODO: Move reporting queries to dedicated analytics database when scaling

namespace App\Livewire\Reports;

use Livewire\Component;

class ReportsContainer extends Component
{
    public string $activeTab = 'source';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('livewire.reports.reports-container')->layout('layouts.app');
    }
}
