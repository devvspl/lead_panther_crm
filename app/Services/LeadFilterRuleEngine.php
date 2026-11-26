<?php

namespace App\Services;

use App\Models\LeadFilterLog;
use App\Models\LeadFilterRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LeadFilterRuleEngine
{
    public const OPERATOR_EQUALS = 'equals';
    public const OPERATOR_NOT_EQUALS = 'not_equals';
    public const OPERATOR_CONTAINS = 'contains';
    public const OPERATOR_NOT_CONTAINS = 'not_contains';
    public const OPERATOR_IS_EMPTY = 'is_empty';
    public const OPERATOR_IS_NOT_EMPTY = 'is_not_empty';

    public static function supportedOperators(): array
    {
        return [
            self::OPERATOR_EQUALS => 'Equals',
            self::OPERATOR_NOT_EQUALS => 'Not Equals',
            self::OPERATOR_CONTAINS => 'Contains',
            self::OPERATOR_NOT_CONTAINS => 'Does Not Contain',
            self::OPERATOR_IS_EMPTY => 'Is Empty',
            self::OPERATOR_IS_NOT_EMPTY => 'Is Not Empty',
        ];
    }

    /**
     * Evaluate a lead against all active filter rules for the given organization & campaign.
     *
     * Rules evaluation logic:
     * 1. If NO rules configured -> Lead is INCLUDED (preserving default behavior).
     * 2. EXCLUDE rules checked first: if any exclude rule matches -> Lead is SKIPPED (action='exclude').
     * 3. INCLUDE rules checked next: if any active include rules exist, at least one must match;
     *    if none match -> Lead is SKIPPED.
     * 4. Otherwise -> Lead is INCLUDED.
     */
    public function evaluateLead(array $leadData, ?int $organizationId, ?int $campaignId = null, ?int $projectId = null): LeadFilterEvaluationResult
    {
        if (!$organizationId) {
            return new LeadFilterEvaluationResult(
                passed: true,
                action: 'include',
                reason: 'No organization context; lead allowed by default.'
            );
        }

        $rules = LeadFilterRule::active()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($campaignId) {
                $q->whereNull('campaign_id');
                if ($campaignId) {
                    $q->orWhere('campaign_id', $campaignId);
                }
            })
            ->where(function ($q) use ($projectId) {
                $q->whereNull('project_id');
                if ($projectId) {
                    $q->orWhere('project_id', $projectId);
                }
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return new LeadFilterEvaluationResult(
                passed: true,
                action: 'include',
                reason: 'No active filtering rules configured.'
            );
        }

        $excludeRules = $rules->where('action', 'exclude');
        $includeRules = $rules->where('action', 'include');

        // 1. Evaluate Exclude Rules: Any match excludes the lead immediately
        foreach ($excludeRules as $rule) {
            $eval = $this->evaluateRule($rule, $leadData);
            if ($eval['matches']) {
                $reason = "Excluded by rule '{$rule->name}' (" . implode(', ', $eval['reasons']) . ')';
                return new LeadFilterEvaluationResult(
                    passed: false,
                    action: 'exclude',
                    reason: $reason,
                    matchedRule: $rule,
                    matchedConditions: $eval['reasons'],
                    details: $eval
                );
            }
        }

        // 2. Evaluate Include Rules: If include rules are defined, at least one must match
        if ($includeRules->isNotEmpty()) {
            $anyIncludeMatched = false;
            $matchedIncludeRule = null;
            $matchedReasons = [];

            foreach ($includeRules as $rule) {
                $eval = $this->evaluateRule($rule, $leadData);
                if ($eval['matches']) {
                    $anyIncludeMatched = true;
                    $matchedIncludeRule = $rule;
                    $matchedReasons = $eval['reasons'];
                    break;
                }
            }

            if (!$anyIncludeMatched) {
                $firstRule = $includeRules->first();
                $reason = "Lead did not meet criteria for inclusion rules (e.g. '{$firstRule->name}')";
                return new LeadFilterEvaluationResult(
                    passed: false,
                    action: 'exclude',
                    reason: $reason,
                    matchedRule: null,
                    matchedConditions: ['Inclusion condition not satisfied'],
                    details: ['include_rules_count' => $includeRules->count()]
                );
            }

            return new LeadFilterEvaluationResult(
                passed: true,
                action: 'include',
                reason: "Matched inclusion rule '{$matchedIncludeRule->name}'",
                matchedRule: $matchedIncludeRule,
                matchedConditions: $matchedReasons
            );
        }

        // 3. All exclude rules evaluated and did not match; no include rules restricting
        return new LeadFilterEvaluationResult(
            passed: true,
            action: 'include',
            reason: 'All filter checks passed successfully.'
        );
    }

    /**
     * Evaluate a single LeadFilterRule against lead data.
     */
    public function evaluateRule(LeadFilterRule|array $rule, array $leadData): array
    {
        $conditions = is_array($rule) ? ($rule['conditions'] ?? []) : ($rule->conditions ?? []);
        $groupLogic = strtoupper(is_array($rule)
            ? ($rule['group_logic'] ?? ($conditions['group_logic'] ?? 'OR'))
            : ($rule->group_logic ?: ($conditions['group_logic'] ?? 'OR')));

        $groups = $conditions['groups'] ?? [];
        if (empty($groups)) {
            return [
                'matches' => false,
                'matched_group' => null,
                'reasons' => ['No condition groups defined in rule'],
            ];
        }

        $groupResults = [];
        $allReasons = [];

        foreach ($groups as $gIndex => $group) {
            $groupLogicWithin = strtoupper($group['logic'] ?? 'AND');
            $conds = $group['conditions'] ?? [];

            if (empty($conds)) {
                $groupResults[] = true;
                continue;
            }

            $conditionResults = [];
            $groupReasons = [];

            foreach ($conds as $cond) {
                $field = $cond['field'] ?? '';
                $operator = $cond['operator'] ?? self::OPERATOR_EQUALS;
                $expected = $cond['value'] ?? '';

                $actualValue = $this->extractFieldValue($leadData, $field);
                $isMatch = $this->evaluateOperator($actualValue, $operator, $expected);
                $conditionResults[] = $isMatch;

                $actualDisplay = is_array($actualValue) ? implode(', ', $actualValue) : ($actualValue !== null ? (string)$actualValue : 'null');
                if ($isMatch) {
                    $groupReasons[] = "{$field} {$operator} \"{$expected}\" (actual: \"{$actualDisplay}\")";
                }
            }

            // Combine conditions inside group (AND vs OR)
            $groupPassed = $groupLogicWithin === 'OR'
                ? in_array(true, $conditionResults, true)
                : !in_array(false, $conditionResults, true);

            $groupResults[] = $groupPassed;

            if ($groupPassed && !empty($groupReasons)) {
                $allReasons[] = implode(" {$groupLogicWithin} ", $groupReasons);
            }
        }

        // Combine groups across the rule (AND vs OR)
        $ruleMatches = $groupLogic === 'AND'
            ? !in_array(false, $groupResults, true)
            : in_array(true, $groupResults, true);

        return [
            'matches' => $ruleMatches,
            'group_results' => $groupResults,
            'reasons' => $allReasons,
        ];
    }

    /**
     * Extract a field value from various lead payload structures.
     * Searches top-level keys, custom_fields dictionary, Meta field_data lists,
     * and performs fuzzy slug matching.
     */
    public function extractFieldValue(array $leadData, string $fieldName): mixed
    {
        $fieldName = trim($fieldName);
        if ($fieldName === '') {
            return null;
        }

        // 1. Direct top-level match
        if (array_key_exists($fieldName, $leadData)) {
            return $leadData[$fieldName];
        }

        // 2. Custom fields dictionary (e.g. from MetaLeadMapper)
        $customFields = $leadData['custom_fields'] ?? [];
        if (is_array($customFields) && array_key_exists($fieldName, $customFields)) {
            return $customFields[$fieldName];
        }

        // 3. Raw Meta field_data format: [['name' => 'want_to_buy_studio', 'values' => ['buy']]]
        $fieldData = $leadData['field_data'] ?? ($leadData['entry'][0]['changes'][0]['value']['field_data'] ?? null);
        if (is_array($fieldData)) {
            foreach ($fieldData as $item) {
                if (isset($item['name']) && strcasecmp((string)$item['name'], $fieldName) === 0) {
                    $vals = $item['values'] ?? [];
                    return is_array($vals) ? (count($vals) === 1 ? $vals[0] : $vals) : $vals;
                }
            }
        }

        // 4. Normalized slug / pattern search across top-level and custom_fields
        $normalizedTarget = preg_replace('/[^a-z0-9]/', '', strtolower($fieldName));
        if ($normalizedTarget !== '') {
            foreach ($leadData as $k => $v) {
                if (preg_replace('/[^a-z0-9]/', '', strtolower((string)$k)) === $normalizedTarget) {
                    return $v;
                }
            }

            if (is_array($customFields)) {
                foreach ($customFields as $k => $v) {
                    if (preg_replace('/[^a-z0-9]/', '', strtolower((string)$k)) === $normalizedTarget) {
                        return $v;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Evaluate an individual comparison operator.
     */
    public function evaluateOperator(mixed $actualValue, string $operator, ?string $expectedValue): bool
    {
        $expected = $expectedValue !== null ? trim((string)$expectedValue) : '';

        // Handle Array Values (e.g. multi-select Meta choices)
        if (is_array($actualValue)) {
            $parts = array_map(fn($v) => trim((string)$v), $actualValue);
            $actualStr = implode(', ', array_filter($parts, fn($p) => $p !== ''));
        } else {
            $actualStr = $actualValue !== null ? trim((string)$actualValue) : '';
        }

        $actualLower = strtolower($actualStr);
        $expectedLower = strtolower($expected);

        return match ($operator) {
            self::OPERATOR_EQUALS => $actualLower === $expectedLower,
            self::OPERATOR_NOT_EQUALS => $actualLower !== $expectedLower,
            self::OPERATOR_CONTAINS => $expectedLower !== '' && str_contains($actualLower, $expectedLower),
            self::OPERATOR_NOT_CONTAINS => $expectedLower === '' || !str_contains($actualLower, $expectedLower),
            self::OPERATOR_IS_EMPTY => $actualValue === null || $actualStr === '',
            self::OPERATOR_IS_NOT_EMPTY => $actualValue !== null && $actualStr !== '',
            default => false,
        };
    }

    /**
     * Validate condition structure before saving.
     */
    public function validateConditionsStructure(array $conditions): void
    {
        $groups = $conditions['groups'] ?? [];
        if (!is_array($groups) || empty($groups)) {
            throw ValidationException::withMessages([
                'conditionGroups' => 'At least one condition group is required.',
            ]);
        }

        foreach ($groups as $gIndex => $group) {
            $conds = $group['conditions'] ?? [];
            if (!is_array($conds) || empty($conds)) {
                throw ValidationException::withMessages([
                    "conditionGroups.{$gIndex}" => "Condition group #" . ($gIndex + 1) . " must contain at least one condition.",
                ]);
            }

            foreach ($conds as $cIndex => $cond) {
                $field = trim($cond['field'] ?? '');
                $op = $cond['operator'] ?? '';

                if ($field === '') {
                    throw ValidationException::withMessages([
                        "conditionGroups.{$gIndex}.{$cIndex}.field" => "Lead field is required for condition in Group #" . ($gIndex + 1) . ".",
                    ]);
                }

                if (!array_key_exists($op, self::supportedOperators())) {
                    throw ValidationException::withMessages([
                        "conditionGroups.{$gIndex}.{$cIndex}.operator" => "Invalid operator selected.",
                    ]);
                }
            }
        }
    }

    /**
     * Preview / dry-run test of rule configuration against a sample lead.
     */
    public function testPreview(array $rulePayload, array $sampleLead): array
    {
        $eval = $this->evaluateRule($rulePayload, $sampleLead);
        $action = $rulePayload['action'] ?? 'include';
        $matches = $eval['matches'];

        // Determine final verdict for this sample lead
        $verdict = ($action === 'exclude')
            ? ($matches ? 'excluded' : 'included')
            : ($matches ? 'included' : 'excluded');

        $reason = $matches
            ? ($action === 'exclude' ? 'Lead matches EXCLUDE conditions and will be SKIPPED.' : 'Lead matches INCLUDE conditions and will be ACCEPTED.')
            : ($action === 'exclude' ? 'Lead does not match exclude conditions; allowed to proceed.' : 'Lead did not match required include conditions; will be SKIPPED.');

        return [
            'verdict' => $verdict,
            'matches' => $matches,
            'action' => $action,
            'reason' => $reason,
            'matched_conditions' => $eval['reasons'],
        ];
    }

    /**
     * Record a filter outcome to the lead_filter_logs table.
     */
    public function recordLog(
        int $organizationId,
        ?int $campaignId,
        ?int $ruleId,
        ?string $leadIdentifier,
        string $action,
        ?string $reason,
        array $matchedConditions = [],
        array $payload = []
    ): ?LeadFilterLog {
        try {
            return LeadFilterLog::create([
                'organization_id' => $organizationId,
                'campaign_id' => $campaignId,
                'rule_id' => $ruleId,
                'lead_identifier' => $leadIdentifier,
                'action' => $action,
                'reason' => $reason,
                'matched_conditions' => $matchedConditions,
                'payload_snapshot' => $payload,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
