<?php

namespace App\Support;

use App\Models\Lead;

class LeadPropertyAndBudgetExtractor
{
    /**
     * Extract actual budget and property type from questions/answers array, raw metadata, or lead.
     *
     * @param array $questions List of ['question' => ..., 'answer' => ...] or associative ['Question' => 'Answer']
     * @return array{budget: ?float, property_type: ?string}
     */
    public static function extractFromQuestions(array $questions): array
    {
        $extractedBudget = null;
        $extractedPropertyType = null;
        $sizeMatch = null;
        $typeMatch = null;

        foreach ($questions as $key => $item) {
            if (is_array($item)) {
                $qClean = strtolower(trim((string) ($item['question'] ?? $item['raw_key'] ?? $key)));
                $aClean = trim((string) ($item['answer'] ?? ''));
            } else {
                $qClean = strtolower(trim((string) $key));
                $aClean = trim((string) $item);
            }

            if ($aClean === '' || $aClean === 'N/A') {
                continue;
            }

            $combined = $qClean . ' ' . strtolower($aClean);

            // 1. Budget extraction
            if (!$extractedBudget) {
                // Priority A: Currency pattern in answer (e.g. ₹34.99 Lakh, 1.5 Cr, 75 Lakhs, 35 Lacs)
                if (preg_match('/(?:₹|rs\.?|inr)?\s*(\d+(?:\.\d+)?)\s*(cr(?:ore)?s?|lakh?s?|lacs?)\b/i', $aClean, $m)) {
                    $num = (float) $m[1];
                    $unit = strtolower($m[2]);
                    $extractedBudget = str_starts_with($unit, 'cr') ? $num * 10000000 : $num * 100000;
                } elseif (str_contains($qClean, 'budget') || str_contains($qClean, 'price') || str_contains($qClean, 'investment') || str_contains($qClean, 'cost')) {
                    // If question specifically asked about budget/price:
                    $cleaned = str_replace([',', ' '], '', $aClean);
                    if (preg_match('/[0-9]+(\.[0-9]+)?/', $cleaned, $nm)) {
                        $num = (float) $nm[0];
                        if (str_contains(strtolower($aClean), 'cr')) {
                            $extractedBudget = $num * 10000000;
                        } elseif (str_contains(strtolower($aClean), 'l') || ($num > 0 && $num < 1000)) {
                            $extractedBudget = $num * 100000;
                        } else {
                            $extractedBudget = $num;
                        }
                    }
                }
            }

            // 2. Size extraction (e.g. 100 Sq. Ft., 1500 sqft, 200 sq yards)
            if (!$sizeMatch && preg_match('/(\d+(?:\.\d+)?\s*(?:sq\.?\s*ft\.?|sqft|sq\s*yards?|sq\s*meters?))/i', $aClean . ' ' . $qClean, $sm)) {
                $sizeMatch = trim($sm[1]);
            }

            // 3. Property Type extraction
            if (!$typeMatch) {
                // Check commercial space types
                if (preg_match('/\b(commercial\s+retail\s+space|commercial\s+office\s+space|retail\s+space|office\s+space|commercial\s+space|shop|showroom|food\s+court|kiosk|warehouse|industrial\s+plot|commercial\s+plot)\b/i', $combined, $tm)) {
                    $typeMatch = ucwords(strtolower($tm[1]));
                }
                // Check residential property types
                elseif (preg_match('/\b(\d+\s*bhk(?:\s+(?:luxury|premium|compact|duplex))?|\d+\s*rk|studio\s+apartment|penthouse|duplex|villa|residential\s+plot|builder\s+floor|floor|apartment|flat)\b/i', $combined, $tm)) {
                    $typeMatch = ucwords(strtolower($tm[1]));
                }
                // Check if question explicitly asked for property type / configuration
                elseif (str_contains($qClean, 'property') || str_contains($qClean, 'configuration') || str_contains($qClean, 'unit type')) {
                    if (strlen($aClean) > 0 && strlen($aClean) <= 50 && !preg_match('/^\d+$/', $aClean)) {
                        $firstPart = trim(explode('-', explode(',', $aClean)[0])[0]);
                        if (!empty($firstPart)) {
                            $typeMatch = ucwords(strtolower($firstPart));
                        }
                    }
                }
            }
        }

        if ($typeMatch && $sizeMatch && !str_contains(strtolower($typeMatch), strtolower($sizeMatch))) {
            $extractedPropertyType = "$typeMatch ($sizeMatch)";
        } elseif ($typeMatch) {
            $extractedPropertyType = $typeMatch;
        } elseif ($sizeMatch) {
            $extractedPropertyType = $sizeMatch;
        }

        return [
            'budget' => $extractedBudget,
            'property_type' => $extractedPropertyType,
        ];
    }

    /**
     * Extract details from a Lead model.
     *
     * @param Lead $lead
     * @return array{budget: ?float, property_type: ?string}
     */
    public static function extractFromLead(Lead $lead): array
    {
        $questions = [];

        // 1. Structured Meta answers metadata
        $answersMeta = $lead->getMetaAttribute('meta_form_answers');
        if (!empty($answersMeta)) {
            $decoded = is_array($answersMeta) ? $answersMeta : json_decode((string) $answersMeta, true);
            if (is_array($decoded)) {
                $questions = array_merge($questions, $decoded);
            }
        }

        // 2. Raw JSON field data
        $rawJsonMeta = $lead->getMetaAttribute('raw_json');
        if (!empty($rawJsonMeta)) {
            $rawDecoded = is_array($rawJsonMeta) ? $rawJsonMeta : json_decode((string) $rawJsonMeta, true);
            if (is_array($rawDecoded)) {
                $fieldData = $rawDecoded['field_data'] ?? $rawDecoded['entry'][0]['changes'][0]['value']['field_data'] ?? null;
                if (is_array($fieldData)) {
                    foreach ($fieldData as $fd) {
                        if (!empty($fd['name']) && !empty($fd['values'])) {
                            $questions[(string)$fd['name']] = is_array($fd['values']) ? implode(', ', $fd['values']) : (string)$fd['values'];
                        }
                    }
                }
            }
        }

        // 3. Parsed Meta Submission
        $parsed = MetaSubmissionParser::parse($lead);
        if (!empty($parsed['questions'])) {
            foreach ($parsed['questions'] as $qa) {
                if (!empty($qa['question']) && !empty($qa['answer'])) {
                    $questions[$qa['question']] = $qa['answer'];
                }
            }
        }

        return self::extractFromQuestions($questions);
    }

    /**
     * Resolve the actual property type for a Lead model.
     * Returns the actual property type string, or null if not specified.
     */
    public static function resolvePropertyType(Lead $lead): ?string
    {
        $existing = trim((string) $lead->property_type);

        // Check if Meta submission or custom metadata has actual data
        $extracted = self::extractFromLead($lead);
        if (!empty($extracted['property_type'])) {
            // Self-heal if the lead had empty or default 'Residential'
            if (empty($existing) || strcasecmp($existing, 'Residential') === 0) {
                if ($existing !== $extracted['property_type']) {
                    $lead->updateQuietly(['property_type' => $extracted['property_type']]);
                }
            }
            return $extracted['property_type'];
        }

        // If existing is explicitly set to something other than the generic default 'Residential'
        if (!empty($existing) && strcasecmp($existing, 'Residential') !== 0) {
            return $existing;
        }

        // Check if project has a property type
        if (!empty($lead->project?->property_type)) {
            return $lead->project->property_type;
        }

        return null;
    }

    /**
     * Resolve the actual budget for a Lead model.
     * Returns the float budget, or null if not specified.
     */
    public static function resolveBudget(Lead $lead): ?float
    {
        $existing = $lead->budget !== null ? (float) $lead->budget : null;

        // Check if Meta submission has actual data
        $extracted = self::extractFromLead($lead);
        if ($extracted['budget'] !== null && $extracted['budget'] > 0) {
            // If existing was the fake default 7500000 or null or <= 0, self-heal
            if ($existing === null || abs($existing - 7500000.00) < 0.01 || $existing <= 0) {
                if ($existing !== $extracted['budget']) {
                    $lead->updateQuietly(['budget' => $extracted['budget']]);
                }
            }
            return $extracted['budget'];
        }

        // If existing is exactly 7500000.00, check if that was the hardcoded fallback
        if ($existing !== null && abs($existing - 7500000.00) < 0.01) {
            $parsedMeta = MetaSubmissionParser::parse($lead);
            if ($parsedMeta['is_meta']) {
                $has75LInSubmission = false;
                foreach ($parsedMeta['questions'] as $qa) {
                    $combined = strtolower(($qa['question'] ?? '') . ' ' . ($qa['answer'] ?? ''));
                    if (str_contains($combined, '75') || str_contains($combined, '7500000')) {
                        $has75LInSubmission = true;
                        break;
                    }
                }
                if (!$has75LInSubmission) {
                    // It was the fake fallback! Clear it to null so fake 75L is not displayed
                    $lead->updateQuietly(['budget' => null]);
                    return null;
                }
            }
        }

        if ($existing !== null && $existing > 0) {
            return $existing;
        }

        return null;
    }

    /**
     * Format a numeric budget cleanly into Indian numbering (Cr / Lakhs / Rupees).
     */
    public static function formatBudget(?float $budget): string
    {
        if ($budget === null || $budget <= 0) {
            return '—';
        }

        if ($budget >= 10000000) {
            $cr = $budget / 10000000;
            return '₹' . (floor($cr) == $cr ? number_format($cr, 0) : number_format($cr, 2)) . ' Cr';
        }

        if ($budget >= 100000) {
            $l = $budget / 100000;
            return '₹' . (floor($l) == $l ? number_format($l, 0) : number_format($l, 2)) . ' Lakhs';
        }

        return '₹' . number_format($budget, 0);
    }

    /**
     * Short format for subtitles or chips (e.g. ₹35.0L, ₹1.5Cr).
     */
    public static function formatShortBudget(?float $budget): string
    {
        if ($budget === null || $budget <= 0) {
            return '';
        }

        if ($budget >= 10000000) {
            $cr = $budget / 10000000;
            return '₹' . (floor($cr) == $cr ? number_format($cr, 0) : number_format($cr, 1)) . 'Cr';
        }

        if ($budget >= 100000) {
            $l = $budget / 100000;
            return '₹' . (floor($l) == $l ? number_format($l, 0) : number_format($l, 1)) . 'L';
        }

        return '₹' . number_format($budget, 0);
    }
}
