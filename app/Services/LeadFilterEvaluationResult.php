<?php

namespace App\Services;

use App\Models\LeadFilterRule;

class LeadFilterEvaluationResult
{
    public function __construct(
        public bool $passed,
        public string $action = 'include', // 'include' or 'exclude'
        public ?string $reason = null,
        public ?LeadFilterRule $matchedRule = null,
        public array $matchedConditions = [],
        public array $details = []
    ) {}
}
