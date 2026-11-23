<?php

namespace App\Support;

use App\Models\Lead;

class LeadPropertyAndBudgetExtractor
{
    /**
     * Extract actual budget, property type, location, and key submission details
     * from questions/answers array, raw metadata, or lead.
     *
     * @param array $questions List of ['question' => ..., 'answer' => ...] or associative ['Question' => 'Answer']
     * @return array{budget: ?float, property_type: ?string, location: ?string, purpose: ?string, call_time: ?string, visit_time: ?string}
     */
    public static function extractFromQuestions(array $questions): array
    {
        $extractedBudget = null;
        $extractedPropertyType = null;
        $extractedLocation = null;
        $sizeMatch = null;
        $typeMatch = null;
        $purpose = null;
        $callTime = null;
        $visitTime = null;

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

            // 4. Location extraction
            if (!$extractedLocation) {
                // Priority A: Question specifically asked for location / sector / locality
                if (str_contains($qClean, 'location') || str_contains($qClean, 'locality') || str_contains($qClean, 'sector') || str_contains($qClean, 'preferred area')) {
                    if ($aClean !== '' && !preg_match('/\b(yes|no|rental|investment|self\s+use)\b/i', $aClean)) {
                        $extractedLocation = ucwords(strtolower($aClean));
                    }
                }
                // Priority B: Known real estate localities & micro-markets
                if (!$extractedLocation) {
                    if (preg_match('/\b(greater\s+noida\s+west|noida\s+extension|greater\s+noida|noida\s+expressway|yamuna\s+expressway|dwarka\s+expressway|golf\s+course\s+ext(?:ension)?|sohna\s+road|new\s+gurgaon|cyber\s+city|dholera(?:\s+sir)?|sector\s+[0-9]+[a-z]?)\b/i', $combined, $lm)) {
                        $extractedLocation = ucwords(strtolower($lm[1]));
                    }
                }
                // Priority C: Pattern "... in <Location>" or "... at <Location>"
                if (!$extractedLocation && preg_match('/\b(?:in|at)\s+([A-Za-z0-9\s]{2,25}?)(?:\?|:|\s*(?:for|with|having|to|or|and|$))/i', $qClean, $lm)) {
                    $cand = trim($lm[1]);
                    if (!preg_match('/\b(a|an|the|any|your|our|this|commercial|residential|retail)\b/i', $cand) && strlen($cand) >= 3) {
                        $extractedLocation = ucwords(strtolower($cand));
                    }
                }
            }

            // 5. Purpose extraction
            if (!$purpose && preg_match('/\b(rental\s+income|self\s+use|own\s+use|investment|capital\s+appreciation|personal\s+use)\b/i', $aClean, $pm)) {
                $purpose = ucwords(strtolower($pm[1]));
            }

            // 6. Suitable Call Time
            if (!$callTime && (str_contains($qClean, 'call') || str_contains($qClean, 'suitable time'))) {
                $callTime = $aClean;
            }

            // 7. Site Visit Schedule
            if (!$visitTime && (str_contains($qClean, 'visit') || str_contains($qClean, 'site visit'))) {
                $visitTime = $aClean;
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
            'location' => $extractedLocation,
            'purpose' => $purpose,
            'call_time' => $callTime,
            'visit_time' => $visitTime,
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
            // Self-heal if the lead had empty, default 'Residential', or only a raw size/area (e.g. '100 Sq. Ft.')
            $isOnlySize = preg_match('/^\d+(?:\.\d+)?\s*(?:sq\.?\s*ft\.?|sqft|sq\s*yards?|sq\s*meters?)$/i', $existing);
            if (empty($existing) || strcasecmp($existing, 'Residential') === 0 || $isOnlySize) {
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
     * Resolve the actual location for a Lead model from Meta submission, custom questions, or project context.
     * Returns the location string, or null if not specified.
     */
    public static function resolveLocation(Lead $lead): ?string
    {
        $existing = trim((string) $lead->location);

        $extracted = self::extractFromLead($lead);
        if (!empty($extracted['location'])) {
            if (empty($existing)) {
                $lead->updateQuietly(['location' => $extracted['location']]);
            }
            return $extracted['location'];
        }

        if (!empty($existing)) {
            return $existing;
        }

        if (!empty($lead->project?->location)) {
            return $lead->project->location;
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
