<?php

namespace App\Livewire\Distribution;

use Livewire\Component;
use App\Models\Project;
use App\Models\Organization;
use App\Models\DistributionRule;
use App\Models\User;

class DistributionRuleForm extends Component
{
    public Project $project;

    public string $ruleType = 'round_robin';
    public ?int $channelPartnerId = null;
    public bool $isActive = true;

    // Config arrays for UI
    public array $locationRows = [];
    public array $priorityUserIds = [];

    public function mount(Project $project): void
    {
        $this->project = $project;

        $existing = DistributionRule::where('project_id', $this->project->id)->latest('id')->first();
        if ($existing) {
            $this->ruleType = $existing->rule_type === 'location' ? 'location' : $existing->rule_type;
            $this->isActive = (bool) $existing->is_active;

            $config = $existing->config ?? [];
            $this->locationRows = $config['location_map'] ?? [
                ['city' => 'Mumbai', 'user_id' => null],
                ['city' => 'Pune', 'user_id' => null],
            ];
            $this->priorityUserIds = $config['priority_users'] ?? [];
        } else {
            $this->locationRows = [
                ['city' => 'Mumbai', 'user_id' => null],
                ['city' => 'Pune', 'user_id' => null],
            ];
        }
    }

    public function addLocationRow(): void
    {
        $this->locationRows[] = ['city' => '', 'user_id' => null];
    }

    public function removeLocationRow(int $index): void
    {
        unset($this->locationRows[$index]);
        $this->locationRows = array_values($this->locationRows);
    }

    public function saveRule(): void
    {
        $config = match ($this->ruleType) {
            'location' => ['location_map' => $this->locationRows],
            'priority' => ['priority_users' => $this->priorityUserIds],
            'availability' => ['only_online' => true],
            default => ['mode' => 'standard', 'partner_id' => $this->channelPartnerId],
        };

        DistributionRule::updateOrCreate(
            [
                'project_id' => $this->project->id,
            ],
            [
                'rule_type' => $this->ruleType,
                'config' => $config,
                'is_active' => $this->isActive,
            ]
        );

        $this->dispatch('toast', type: 'success', message: 'Lead distribution rule saved successfully.');
    }

    public function render()
    {
        $channelPartners = Organization::where('type', 'channel_partner')->get();
        $executives = User::all();

        return view('livewire.distribution.distribution-rule-form', [
            'channelPartners' => $channelPartners,
            'executives' => $executives,
        ])->layout('layouts.app');
    }
}
