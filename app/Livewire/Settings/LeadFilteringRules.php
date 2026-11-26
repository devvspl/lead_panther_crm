<?php

namespace App\Livewire\Settings;

use App\Models\Campaign;
use App\Models\LeadFilterLog;
use App\Models\LeadFilterRule;
use App\Models\MetaAdRecord;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\LeadFilterRuleEngine;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeadFilteringRules extends Component
{
    #[Locked]
    public ?int $organizationId = null;

    public ?int $filterCampaignId = null;
    public ?int $filterProjectId = null;
    public string $search = '';

    // Modal state for Rule Editor
    public bool $showRuleModal = false;
    public ?int $editingRuleId = null;
    public string $ruleName = '';
    public string $ruleDescription = '';
    public string $ruleScope = 'all'; // 'all', 'camp_1', 'proj_1'
    public string $ruleScopeType = 'all'; // 'all', 'campaign', 'project'
    public ?int $ruleCampaignId = null;
    public ?int $ruleProjectId = null;
    public string $ruleAction = 'include'; // 'include' or 'exclude'
    public string $ruleConditionLogic = 'AND'; // 'AND' or 'OR'
    public string $ruleGroupLogic = 'OR'; // 'AND' or 'OR' between groups
    public array $conditions = [];
    public array $conditionGroups = [];

    // Modal state for Test Rules Preview
    public bool $showTestModal = false;
    public ?int $testCampaignId = null;
    public ?int $testProjectId = null;
    public array $testFields = [];
    public ?array $testResult = null;

    // View sub-tab: 'rules' or 'logs'
    public string $subTab = 'rules';

    public function mount(?int $organizationId = null): void
    {
        $this->organizationId = $organizationId ?: (auth()->user()?->organization_id ?: (Organization::first()?->id ?? 1));
        $this->resetConditions();
        $this->resetTestFields();
    }

    public function updatedRuleScope(string $val): void
    {
        if (str_starts_with($val, 'camp_')) {
            $this->ruleScopeType = 'campaign';
            $this->ruleCampaignId = (int) substr($val, 5);
            $this->ruleProjectId = null;
        } elseif (str_starts_with($val, 'proj_')) {
            $this->ruleScopeType = 'project';
            $this->ruleProjectId = (int) substr($val, 5);
            $this->ruleCampaignId = null;
        } else {
            $this->ruleScopeType = 'all';
            $this->ruleCampaignId = null;
            $this->ruleProjectId = null;
        }
    }

    public function updatedConditions(): void
    {
        if (isset($this->conditionGroups[0])) {
            $this->conditionGroups[0]['conditions'] = $this->conditions;
        }
    }

    public function updatedRuleConditionLogic(): void
    {
        if (isset($this->conditionGroups[0])) {
            $this->conditionGroups[0]['logic'] = $this->ruleConditionLogic;
        }
    }

    public function updatedConditionGroups(): void
    {
        if (!empty($this->conditionGroups[0]['conditions'])) {
            $this->conditions = $this->conditionGroups[0]['conditions'];
            $this->ruleConditionLogic = $this->conditionGroups[0]['logic'] ?? 'AND';
        }
    }

    public function resetConditions(): void
    {
        $this->conditions = [
            ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => ''],
        ];
        $this->conditionGroups = [
            [
                'logic' => $this->ruleConditionLogic ?: 'AND',
                'conditions' => $this->conditions,
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

    public function openCreateRuleModal(?int $campaignId = null, ?int $projectId = null): void
    {
        $this->resetValidation();
        $this->editingRuleId = null;
        $this->ruleName = '';
        $this->ruleDescription = '';
        $this->ruleCampaignId = $campaignId ?: $this->filterCampaignId;
        $this->ruleProjectId = $projectId ?: $this->filterProjectId;

        if ($this->ruleCampaignId) {
            $this->ruleScope = 'camp_' . $this->ruleCampaignId;
            $this->ruleScopeType = 'campaign';
        } elseif ($this->ruleProjectId) {
            $this->ruleScope = 'proj_' . $this->ruleProjectId;
            $this->ruleScopeType = 'project';
        } else {
            $this->ruleScope = 'all';
            $this->ruleScopeType = 'all';
        }

        $this->ruleAction = 'include';
        $this->ruleConditionLogic = 'AND';
        $this->ruleGroupLogic = 'OR';
        $this->resetConditions();

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
        $this->ruleProjectId = $rule->project_id;

        if ($rule->campaign_id) {
            $this->ruleScope = 'camp_' . $rule->campaign_id;
            $this->ruleScopeType = 'campaign';
        } elseif ($rule->project_id) {
            $this->ruleScope = 'proj_' . $rule->project_id;
            $this->ruleScopeType = 'project';
        } else {
            $this->ruleScope = 'all';
            $this->ruleScopeType = 'all';
        }

        $this->ruleAction = $rule->action;
        $this->ruleGroupLogic = $rule->group_logic ?: 'OR';

        $rawGroups = $rule->conditions['groups'] ?? [];
        if (!empty($rawGroups)) {
            $this->conditionGroups = $rawGroups;
            $this->ruleConditionLogic = $rawGroups[0]['logic'] ?? 'AND';
            $this->conditions = $rawGroups[0]['conditions'] ?? [
                ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => ''],
            ];
        } else {
            $this->resetConditions();
        }

        $this->showRuleModal = true;
        $this->dispatch('open-modal', 'lead-filter-rule-editor');
    }

    public function addConditionRow(): void
    {
        $this->conditions[] = [
            'field' => 'want_to_buy_studio',
            'operator' => 'equals',
            'value' => '',
        ];
        $this->conditionGroups[0]['conditions'] = $this->conditions;
    }

    public function removeConditionRow(int $index): void
    {
        if (count($this->conditions) > 1) {
            unset($this->conditions[$index]);
            $this->conditions = array_values($this->conditions);
            $this->conditionGroups[0]['conditions'] = $this->conditions;
        }
    }

    public function addConditionGroup(): void
    {
        $this->conditionGroups[] = [
            'logic' => 'AND',
            'conditions' => [
                ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => ''],
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
                'field' => 'want_to_buy_studio',
                'operator' => 'equals',
                'value' => '',
            ];
            if ($groupIndex === 0) {
                $this->conditions = $this->conditionGroups[0]['conditions'];
            }
        }
    }

    public function removeCondition(int $groupIndex, int $conditionIndex): void
    {
        if (isset($this->conditionGroups[$groupIndex]['conditions'][$conditionIndex])) {
            if (count($this->conditionGroups[$groupIndex]['conditions']) > 1) {
                unset($this->conditionGroups[$groupIndex]['conditions'][$conditionIndex]);
                $this->conditionGroups[$groupIndex]['conditions'] = array_values($this->conditionGroups[$groupIndex]['conditions']);
                if ($groupIndex === 0) {
                    $this->conditions = $this->conditionGroups[0]['conditions'];
                }
            }
        }
    }

    public function saveRule(): void
    {
        // Resolve scope from ruleScope single select if present
        if (!empty($this->ruleScope) && $this->ruleScope !== 'all') {
            if (str_starts_with($this->ruleScope, 'camp_')) {
                $this->ruleScopeType = 'campaign';
                $this->ruleCampaignId = (int) substr($this->ruleScope, 5);
                $this->ruleProjectId = null;
            } elseif (str_starts_with($this->ruleScope, 'proj_')) {
                $this->ruleScopeType = 'project';
                $this->ruleProjectId = (int) substr($this->ruleScope, 5);
                $this->ruleCampaignId = null;
            }
        } elseif ($this->ruleScope === 'all' && empty($this->ruleCampaignId) && empty($this->ruleProjectId)) {
            $this->ruleScopeType = 'all';
            $this->ruleCampaignId = null;
            $this->ruleProjectId = null;
        }

        $this->validate([
            'ruleName' => 'required|string|max:120',
            'ruleAction' => 'required|in:include,exclude',
            'ruleScopeType' => 'required|in:all,campaign,project',
            'ruleCampaignId' => 'nullable|integer',
            'ruleProjectId' => 'nullable|integer',
        ]);

        $campaignId = null;
        $projectId = null;
        if ($this->ruleScopeType === 'campaign') {
            $campaignId = $this->ruleCampaignId ?: null;
        } elseif ($this->ruleScopeType === 'project') {
            $projectId = $this->ruleProjectId ?: null;
        }

        // Keep condition groups synced with condition builder
        $groups = !empty($this->conditions)
            ? [
                [
                    'logic' => $this->ruleConditionLogic ?: 'AND',
                    'conditions' => $this->conditions,
                ],
            ]
            : ($this->conditionGroups ?: [
                [
                    'logic' => 'AND',
                    'conditions' => [['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => '']],
                ],
            ]);

        $conditionsPayload = [
            'group_logic' => $this->ruleGroupLogic ?: 'OR',
            'groups' => $groups,
        ];

        $engine = app(LeadFilterRuleEngine::class);
        $engine->validateConditionsStructure($conditionsPayload);

        LeadFilterRule::updateOrCreate(
            [
                'id' => $this->editingRuleId,
                'organization_id' => $this->organizationId,
            ],
            [
                'name' => trim($this->ruleName),
                'description' => trim($this->ruleDescription) ?: null,
                'campaign_id' => $campaignId,
                'project_id' => $projectId,
                'created_by' => auth()->id() ?? 1,
                'action' => $this->ruleAction,
                'group_logic' => $this->ruleGroupLogic ?: 'OR',
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
    public function openTestModal(?int $campaignId = null, ?int $projectId = null): void
    {
        $this->testCampaignId = $campaignId ?: $this->filterCampaignId;
        $this->testProjectId = $projectId ?: $this->filterProjectId;
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
        $eval = $engine->evaluateLead($samplePayload, $this->organizationId, $this->testCampaignId, $this->testProjectId);

        $this->testResult = [
            'passed' => $eval->passed,
            'action' => $eval->action,
            'verdict' => $eval->passed ? 'INCLUDED' : 'EXCLUDED / SKIPPED',
            'reason' => $eval->reason,
            'matched_rule' => $eval->matchedRule?->name,
            'matched_conditions' => $eval->matchedConditions,
        ];
    }

    public function getLeadFieldOptionsProperty(): array
    {
        return [
            'want_to_buy_studio' => 'want_to_buy_studio',
            'free_site_visit' => 'free_site_visit',
            'city' => 'city',
            'location' => 'location',
            'budget' => 'budget',
            'property_type' => 'property_type',
            'configuration' => 'configuration',
            'requirement' => 'requirement',
            'full_name' => 'full_name',
            'phone_number' => 'phone_number',
            'email' => 'email',
            'campaign_name' => 'campaign_name',
            'form_name' => 'form_name',
            'platform' => 'platform',
        ];
    }

    public function getOperatorOptionsProperty(): array
    {
        return LeadFilterRuleEngine::supportedOperators();
    }

    public function getScopeOptionsProperty(): array
    {
        $org = Organization::find($this->organizationId);
        $options = [
            'all' => 'All Campaigns',
        ];

        if ($org) {
            $projects = $org->projects()->withoutGlobalScopes()->orderBy('projects.name')->pluck('projects.name', 'projects.id')->all();
            $projectIds = array_keys($projects);

            $camps = (!empty($projectIds))
                ? Campaign::withoutGlobalScopes()->whereIn('project_id', $projectIds)->orderBy('campaigns.name')->pluck('campaigns.name', 'campaigns.id')->all()
                : [];

            if (empty($camps)) {
                $clientIds = User::where('organization_id', $org->id)->pluck('id')->all();
                $camps = MetaAdRecord::where('kind', 'campaign')
                    ->where(function ($q) use ($clientIds, $projectIds) {
                        if (!empty($projectIds)) $q->whereIn('project_id', $projectIds);
                        if (!empty($clientIds)) $q->orWhereIn('client_id', $clientIds);
                    })
                    ->pluck('name', 'id')
                    ->all();
            }

            foreach ($camps as $cId => $cName) {
                $displayName = $cName . (str_contains($cName, '(') ? '' : ' (FB)');
                $options['camp_' . $cId] = $displayName;
            }

            foreach ($projects as $pId => $pName) {
                $options['proj_' . $pId] = 'Project: ' . $pName;
            }
        }

        return $options;
    }

    public function render()
    {
        $query = LeadFilterRule::with(['campaign', 'project', 'creator'])
            ->where('organization_id', $this->organizationId);

        if ($this->filterCampaignId) {
            $query->where(function ($q) {
                $q->whereNull('campaign_id')->orWhere('campaign_id', $this->filterCampaignId);
            });
        }

        if ($this->filterProjectId) {
            $query->where(function ($q) {
                $q->whereNull('project_id')->orWhere('project_id', $this->filterProjectId);
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
        $projectOptions = $org
            ? $org->projects()->withoutGlobalScopes()->orderBy('projects.name')->pluck('projects.name', 'projects.id')->all()
            : [];

        $projectIds = array_keys($projectOptions);

        $campaignOptions = (!empty($projectIds))
            ? Campaign::withoutGlobalScopes()
                ->whereIn('project_id', $projectIds)
                ->orderBy('campaigns.name')
                ->pluck('campaigns.name', 'campaigns.id')
                ->all()
            : [];

        if (empty($campaignOptions) && $org) {
            $clientIds = User::where('organization_id', $org->id)->pluck('id')->all();
            $metaCampNames = MetaAdRecord::where('kind', 'campaign')
                ->where(function ($q) use ($clientIds, $projectIds) {
                    if (!empty($projectIds)) $q->whereIn('project_id', $projectIds);
                    if (!empty($clientIds)) $q->orWhereIn('client_id', $clientIds);
                })
                ->pluck('name', 'id')
                ->all();
            if (!empty($metaCampNames)) {
                $campaignOptions = $metaCampNames;
            }
        }

        $recentLogs = LeadFilterLog::with('rule', 'campaign')
            ->where('organization_id', $this->organizationId)
            ->latest('id')
            ->limit(38)
            ->get();

        return view('livewire.settings.lead-filtering-rules', [
            'rules' => $rules,
            'campaigns' => $campaignOptions,
            'projects' => $projectOptions,
            'recentLogs' => $recentLogs,
            'leadFieldOptions' => $this->leadFieldOptions,
            'operatorOptions' => $this->operatorOptions,
            'scopeOptions' => $this->scopeOptions,
        ])->layout('layouts.app');
    }
}
