<?php

namespace App\Livewire\Settings;

use App\Models\Campaign;
use App\Models\LeadFilterLog;
use App\Models\LeadFilterRule;
use App\Models\Organization;
use App\Services\LeadFilterRuleEngine;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeadFilteringRules extends Component
{
    #[Locked]
    public ?int $organizationId = null;

    public ?int $filterCampaignId = null;
    public string $search = '';

    // Modal state for Rule Editor
    public bool $showRuleModal = false;
    public ?int $editingRuleId = null;
    public string $ruleName = '';
    public string $ruleDescription = '';
    public ?int $ruleCampaignId = null;
    public string $ruleAction = 'include'; // 'include' or 'exclude'
    public string $ruleGroupLogic = 'OR'; // 'AND' or 'OR' between groups
    public array $conditionGroups = [];

    // Modal state for Test Rules Preview
    public bool $showTestModal = false;
    public ?int $testCampaignId = null;
    public array $testFields = [];
    public ?array $testResult = null;

    // View sub-tab: 'rules' or 'logs'
    public string $subTab = 'rules';

    public function mount(?int $organizationId = null): void
    {
        $this->organizationId = $organizationId ?: (auth()->user()?->organization_id ?: (Organization::first()?->id ?? 1));
        $this->resetConditionGroups();
        $this->resetTestFields();
    }

    public function resetConditionGroups(): void
    {
        $this->conditionGroups = [
            [
                'logic' => 'AND',
                'conditions' => [
                    ['field' => '', 'operator' => 'equals', 'value' => ''],
                ],
            ],
        ];
    }

    public function resetTestFields(): void
    {
        $this->testFields = [
            ['field' => 'want_to_buy_studio', 'value' => 'buy'],
            ['field' => 'free_site_visit', 'value' => 'immediate'],
            ['field' => 'city', 'value' => 'Noida'],
        ];
        $this->testResult = null;
    }

    public function openCreateRuleModal(?int $campaignId = null): void
    {
        $this->resetValidation();
        $this->editingRuleId = null;
        $this->ruleName = '';
        $this->ruleDescription = '';
        $this->ruleCampaignId = $campaignId ?: $this->filterCampaignId;
        $this->ruleAction = 'include';
        $this->ruleGroupLogic = 'OR';
        $this->resetConditionGroups();

        $this->showRuleModal = true;
        $this->dispatch('open-modal', 'lead-filter-rule-editor');
    }

    public function openEditRuleModal(int $ruleId): void
    {
        $this->resetValidation();
        $rule = LeadFilterRule::where('organization_id', $this->organizationId)->findOrFail($ruleId);

        $this->editingRuleId = $rule->id;
        $this->ruleName = $rule->name;
        $this->ruleDescription = $rule->description ?? '';
        $this->ruleCampaignId = $rule->campaign_id;
        $this->ruleAction = $rule->action;
        $this->ruleGroupLogic = $rule->group_logic ?: 'OR';

        $rawGroups = $rule->conditions['groups'] ?? [];
        if (empty($rawGroups)) {
            $this->resetConditionGroups();
        } else {
            $this->conditionGroups = $rawGroups;
        }

        $this->showRuleModal = true;
        $this->dispatch('open-modal', 'lead-filter-rule-editor');
    }

    public function addConditionGroup(): void
    {
        $this->conditionGroups[] = [
            'logic' => 'AND',
            'conditions' => [
                ['field' => '', 'operator' => 'equals', 'value' => ''],
            ],
        ];
    }

    public function removeConditionGroup(int $groupIndex): void
    {
        if (count($this->conditionGroups) > 1) {
            unset($this->conditionGroups[$groupIndex]);
            $this->conditionGroups = array_values($this->conditionGroups);
        }
    }

    public function addCondition(int $groupIndex): void
    {
        if (isset($this->conditionGroups[$groupIndex])) {
            $this->conditionGroups[$groupIndex]['conditions'][] = [
                'field' => '',
                'operator' => 'equals',
                'value' => '',
            ];
        }
    }

    public function removeCondition(int $groupIndex, int $conditionIndex): void
    {
        if (isset($this->conditionGroups[$groupIndex]['conditions'][$conditionIndex])) {
            if (count($this->conditionGroups[$groupIndex]['conditions']) > 1) {
                unset($this->conditionGroups[$groupIndex]['conditions'][$conditionIndex]);
                $this->conditionGroups[$groupIndex]['conditions'] = array_values($this->conditionGroups[$groupIndex]['conditions']);
            }
        }
    }

    public function saveRule(): void
    {
        $this->validate([
            'ruleName' => 'required|string|max:120',
            'ruleAction' => 'required|in:include,exclude',
            'ruleGroupLogic' => 'required|in:AND,OR',
            'ruleCampaignId' => 'nullable|integer',
        ]);

        $engine = app(LeadFilterRuleEngine::class);
        $conditionsPayload = [
            'group_logic' => $this->ruleGroupLogic,
            'groups' => $this->conditionGroups,
        ];
        $engine->validateConditionsStructure($conditionsPayload);

        LeadFilterRule::updateOrCreate(
            [
                'id' => $this->editingRuleId,
                'organization_id' => $this->organizationId,
            ],
            [
                'name' => trim($this->ruleName),
                'description' => trim($this->ruleDescription) ?: null,
                'campaign_id' => $this->ruleCampaignId ?: null,
                'action' => $this->ruleAction,
                'group_logic' => $this->ruleGroupLogic,
                'conditions' => $conditionsPayload,
                'is_active' => true,
            ]
        );

        $this->dispatch('close-modal', 'lead-filter-rule-editor');
        $this->showRuleModal = false;
        $this->dispatch('toast', type: 'success', message: $this->editingRuleId ? 'Filter rule updated successfully.' : 'New filter rule created.');
    }

    public function toggleRuleActive(int $ruleId): void
    {
        $rule = LeadFilterRule::where('organization_id', $this->organizationId)->findOrFail($ruleId);
        $rule->update(['is_active' => !$rule->is_active]);

        $status = $rule->is_active ? 'enabled' : 'disabled';
        $this->dispatch('toast', type: 'success', message: "Rule '{$rule->name}' has been {$status}.");
    }

    public function deleteRule(int $ruleId): void
    {
        $rule = LeadFilterRule::where('organization_id', $this->organizationId)->findOrFail($ruleId);
        $name = $rule->name;
        $rule->delete();

        $this->dispatch('toast', type: 'success', message: "Rule '{$name}' deleted.");
    }

    // Testing / Preview Methods
    public function openTestModal(?int $campaignId = null): void
    {
        $this->testCampaignId = $campaignId ?: $this->filterCampaignId;
        $this->showTestModal = true;
        $this->dispatch('open-modal', 'lead-filter-test-preview');
        $this->runTestPreview();
    }

    public function addTestField(): void
    {
        $this->testFields[] = ['field' => '', 'value' => ''];
    }

    public function removeTestField(int $index): void
    {
        unset($this->testFields[$index]);
        $this->testFields = array_values($this->testFields);
    }

    public function runTestPreview(): void
    {
        $samplePayload = [];
        $customFields = [];
        foreach ($this->testFields as $item) {
            $f = trim($item['field'] ?? '');
            $v = trim($item['value'] ?? '');
            if ($f !== '') {
                $samplePayload[$f] = $v;
                $customFields[$f] = $v;
            }
        }
        $samplePayload['custom_fields'] = $customFields;

        $engine = app(LeadFilterRuleEngine::class);
        $eval = $engine->evaluateLead($samplePayload, $this->organizationId, $this->testCampaignId);

        $this->testResult = [
            'passed' => $eval->passed,
            'action' => $eval->action,
            'verdict' => $eval->passed ? 'INCLUDED' : 'EXCLUDED / SKIPPED',
            'reason' => $eval->reason,
            'matched_rule' => $eval->matchedRule?->name,
            'matched_conditions' => $eval->matchedConditions,
        ];
    }

    public function render()
    {
        $query = LeadFilterRule::with('campaign')
            ->where('organization_id', $this->organizationId);

        if ($this->filterCampaignId) {
            $query->where(function ($q) {
                $q->whereNull('campaign_id')->orWhere('campaign_id', $this->filterCampaignId);
            });
        }

        if (!empty($this->search)) {
            $s = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                    ->orWhere('description', 'like', $s);
            });
        }

        $rules = $query->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        $org = Organization::find($this->organizationId);
        $campaigns = $org
            ? Campaign::withoutGlobalScopes()
                ->whereIn('project_id', $org->projects()->withoutGlobalScopes()->select('projects.id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all()
            : [];

        $recentLogs = LeadFilterLog::with('rule', 'campaign')
            ->where('organization_id', $this->organizationId)
            ->latest('id')
            ->limit(20)
            ->get();

        return view('livewire.settings.lead-filtering-rules', [
            'rules' => $rules,
            'campaigns' => $campaigns,
            'recentLogs' => $recentLogs,
            'supportedOperators' => LeadFilterRuleEngine::supportedOperators(),
        ])->layout('layouts.app');
    }
}
