<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class StatCard extends Component
{
    public string $title = 'Metric';
    public string $label = '';
    public string|int $value = 0;
    public string $delta = '0%';
    public bool $isPositive = true;
    public string $icon = 'chart';

    public function mount(
        string $title = '',
        string $label = '',
        $value = 0,
        string $delta = '0%',
        bool $isPositive = true,
        string $icon = 'chart'
    ) {
        $this->title = $title ?: $label;
        $this->label = $label ?: $title;
        $this->value = $value;
        $this->delta = $delta;
        $this->isPositive = $isPositive;
        $this->icon = $icon;
    }

    public function render()
    {
        return view('livewire.dashboard.stat-card');
    }
}
