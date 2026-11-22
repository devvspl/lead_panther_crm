<?php

namespace App\Support;

use App\Models\Lead;

class MetaSubmissionParser
{
    /**
     * Standard contact/system fields in Meta forms that are already mapped
     * to primary Lead fields (name, mobile, email) and should not clutter the Q&A list.
     */
    protected static array $ignoredKeys = [
        'full_name',
        'name',
        'first_name',
        'last_name',
        'phone_number',
        'phone',
        'mobile',
        'mobile_number',
        'contact_number',
        'whatsapp_number',
        'email',
        'email_address',
        'inbox_url',
    ];

    /**
     * Parse Meta submission data from Lead model metadata and requirement.
     *
     * @param Lead|null $lead
     * @return array{
     *     is_meta: bool,
     *     has_submission_data: bool,
     *     questions: array<int, array{raw_key: string, question: string, answer: string}>,
     *     context: array{campaign: string, adset: string, ad: string, form: string, form_id: string, leadgen_id: string},
     *     raw_requirement: string
     * }
     */
    public static function parse(?Lead $lead): array
    {
        $defaultContext = [
            'campaign' => 'N/A',
            'adset' => 'N/A',
            'ad' => 'N/A',
            'form' => 'N/A',
            'form_id' => 'N/A',
            'leadgen_id' => 'N/A',
        ];

        if (! $lead) {
            return [
                'is_meta' => false,
                'has_submission_data' => false,
                'questions' => [],
                'context' => $defaultContext,
                'raw_requirement' => '',
            ];
        }

        $context = [
            'campaign' => $lead->getMetaAttribute('meta_campaign_name') ?: $lead->campaign?->name,
            'adset' => $lead->getMetaAttribute('meta_adset_name'),
            'ad' => $lead->getMetaAttribute('meta_ad_name'),
            'form' => $lead->getMetaAttribute('meta_form_name'),
            'form_id' => $lead->getMetaAttribute('meta_form_id'),
            'leadgen_id' => $lead->getMetaAttribute('meta_leadgen_id'),
        ];

        $req = (string) $lead->requirement;
        $answersMeta = $lead->getMetaAttribute('meta_form_answers');
        $rawJsonMeta = $lead->getMetaAttribute('raw_json');

        $isMeta = false;
        if (
            ! empty($answersMeta) ||
            ! empty($context['leadgen_id']) ||
            ! empty($context['form_id']) ||
            str_contains($req, 'Meta Lead Submission:') ||
            str_contains($req, 'Meta Context:') ||
            ($lead->leadSource && strtolower((string) $lead->leadSource->code) === 'meta') ||
            ($lead->leadSource && str_contains(strtolower((string) $lead->leadSource->name), 'meta')) ||
            (isset($lead->source) && strtolower((string) $lead->source) === 'meta')
        ) {
            $isMeta = true;
        }

        $questions = [];

        // 1. Primary approach: parse from structured meta_form_answers
        if (! empty($answersMeta)) {
            $decoded = is_array($answersMeta) ? $answersMeta : json_decode((string) $answersMeta, true);
            if (is_array($decoded) && ! empty($decoded)) {
                foreach ($decoded as $key => $val) {
                    $keyStr = (string) $key;
                    if (self::shouldIgnoreKey($keyStr)) {
                        continue;
                    }

                    $answerStr = self::formatAnswer($val);

                    $questions[] = [
                        'raw_key' => $keyStr,
                        'question' => self::formatQuestionLabel($keyStr),
                        'answer' => $answerStr,
                    ];
                }
            }
        }

        // 2. Secondary approach: if no questions found, check raw_json for field_data
        if (empty($questions) && ! empty($rawJsonMeta)) {
            $rawDecoded = is_array($rawJsonMeta) ? $rawJsonMeta : json_decode((string) $rawJsonMeta, true);
            if (is_array($rawDecoded)) {
                $fieldData = $rawDecoded['field_data'] ?? $rawDecoded['entry'][0]['changes'][0]['value']['field_data'] ?? null;
                if (is_array($fieldData)) {
                    foreach ($fieldData as $field) {
                        $fName = (string) ($field['name'] ?? '');
                        if (empty($fName) || self::shouldIgnoreKey($fName)) {
                            continue;
                        }
                        $fValues = $field['values'] ?? [];
                        $answerStr = self::formatAnswer($fValues);

                        $questions[] = [
                            'raw_key' => $fName,
                            'question' => self::formatQuestionLabel($fName),
                            'answer' => $answerStr,
                        ];
                    }
                }
            }
        }

        // 3. Fallback approach: parse from requirement text if questions still empty
        if (empty($questions) && (str_contains($req, 'Meta Lead Submission:') || str_contains($req, 'Meta Context:'))) {
            $normalizedReq = str_replace(["\r\n", "\r"], "\n", $req);
            // Bullet characters (•, â€¢, Ã¢â‚¬Â¢) separate items even if all on a single line without newlines
            $normalizedReq = preg_replace('/(?:\s*(?:Ã¢â‚¬Â¢|â€¢|•)\s*|\n+)/u', "\n", $normalizedReq);
            $lines = explode("\n", $normalizedReq);

            foreach ($lines as $line) {
                $cleanLine = trim($line);
                // Strip leading "Meta Lead Submission:" prefix if present
                if (str_starts_with($cleanLine, 'Meta Lead Submission:')) {
                    $cleanLine = trim(substr($cleanLine, strlen('Meta Lead Submission:')));
                }
                // Strip leading bullet characters (handling double-encoded Ã¢â‚¬Â¢, â€¢, standard •, -, *)
                $cleanLine = preg_replace('/^(?:Ã¢â‚¬Â¢|â€¢|[-*•]|\s)+/u', '', $cleanLine);
                $cleanLine = trim((string) $cleanLine);

                if (empty($cleanLine)) {
                    continue;
                }

                if (str_starts_with($cleanLine, 'Meta Context:')) {
                    self::extractContextFromString(substr($cleanLine, strlen('Meta Context:')), $context);
                    continue;
                }

                $colonPos = mb_strpos($cleanLine, ':');
                if ($colonPos !== false) {
                    $q = trim(mb_substr($cleanLine, 0, $colonPos));
                    $a = trim(mb_substr($cleanLine, $colonPos + 1));
                    if (! empty($q) && ! self::shouldIgnoreKey($q)) {
                        $questions[] = [
                            'raw_key' => $q,
                            'question' => self::formatQuestionLabel($q),
                            'answer' => $a !== '' ? $a : 'N/A',
                        ];
                    }
                }
            }
        }

        // Also check if requirement contains Meta Context line to enrich missing context fields
        if (str_contains($req, 'Meta Context:')) {
            $normalizedReq = str_replace(["\r\n", "\r"], "\n", $req);
            $normalizedReq = preg_replace('/(?:\s*(?:Ã¢â‚¬Â¢|â€¢|•)\s*|\n+)/u', "\n", $normalizedReq);
            foreach (explode("\n", $normalizedReq) as $l) {
                $trimmed = trim($l);
                $cleanL = preg_replace('/^(?:Ã¢â‚¬Â¢|â€¢|[-*•]|\s)+/u', '', $trimmed);
                if (str_starts_with($cleanL, 'Meta Context:')) {
                    self::extractContextFromString(substr($cleanL, strlen('Meta Context:')), $context);
                    break;
                }
            }
        }

        // Resolve Form display name
        if (empty($context['form']) && ! empty($context['form_id'])) {
            $context['form'] = '#' . $context['form_id'];
        }

        // Fill missing context fields with 'N/A'
        foreach (['campaign', 'adset', 'ad', 'form', 'form_id', 'leadgen_id'] as $k) {
            $val = trim((string) ($context[$k] ?? ''));
            $context[$k] = $val !== '' ? $val : 'N/A';
        }

        return [
            'is_meta' => $isMeta,
            'has_submission_data' => ! empty($questions),
            'questions' => $questions,
            'context' => $context,
            'raw_requirement' => $req,
        ];
    }

    /**
     * Check if a field key is a standard contact field or internal field to ignore in Q&A.
     */
    public static function shouldIgnoreKey(string $key): bool
    {
        $normalizedUnderscore = strtolower(trim(str_replace(['?', ':', '-', ' '], '_', $key)));
        $normalizedSpace = strtolower(trim(str_replace(['?', ':', '-', '_'], ' ', $key)));

        if (in_array($normalizedUnderscore, self::$ignoredKeys, true)) {
            return true;
        }

        $ignoredPhrases = [
            'full name',
            'name',
            'first name',
            'last name',
            'phone number',
            'phone',
            'mobile',
            'mobile number',
            'contact number',
            'whatsapp number',
            'email',
            'email address',
            'inbox url',
        ];

        return in_array($normalizedSpace, $ignoredPhrases, true);
    }

    /**
     * Format a raw question key into a clean, human-readable label.
     */
    public static function formatQuestionLabel(string $rawKey): string
    {
        // Strip trailing or leading punctuation and whitespace
        $clean = trim($rawKey, "?:!-_ \t\n\r\0\x0B");

        // Replace underscores and hyphens with spaces
        $clean = str_replace(['_', '-'], ' ', $clean);

        // Collapse multiple whitespace
        $clean = preg_replace('/\s+/u', ' ', $clean);

        // Convert to title case while preserving existing emojis/symbols
        return ucwords(trim($clean));
    }

    /**
     * Format answer value (handles string, array, etc.) and preserves Unicode/Hindi.
     */
    public static function formatAnswer(mixed $val): string
    {
        if (is_array($val)) {
            $flat = [];
            foreach ($val as $item) {
                if (is_scalar($item)) {
                    $itemStr = trim((string) $item);
                    if ($itemStr !== '') {
                        $flat[] = $itemStr;
                    }
                }
            }
            $val = implode(', ', $flat);
        }

        $str = trim((string) $val);

        return $str !== '' ? $str : 'N/A';
    }

    /**
     * Extract key-value pairs from a "Meta Context: Campaign: X | AdSet: Y | Ad: Z | Form: #W" string.
     */
    protected static function extractContextFromString(string $contextStr, array &$context): void
    {
        $parts = explode('|', $contextStr);
        foreach ($parts as $part) {
            $kv = explode(':', trim($part), 2);
            if (count($kv) === 2) {
                $k = strtolower(trim($kv[0]));
                $v = trim($kv[1]);
                if ($k === 'campaign' && (empty($context['campaign']) || $context['campaign'] === 'N/A')) {
                    $context['campaign'] = $v;
                } elseif (($k === 'adset' || $k === 'ad set') && (empty($context['adset']) || $context['adset'] === 'N/A')) {
                    $context['adset'] = $v;
                } elseif (($k === 'ad' || $k === 'creative') && (empty($context['ad']) || $context['ad'] === 'N/A')) {
                    $context['ad'] = $v;
                } elseif ($k === 'form') {
                    if (empty($context['form']) || $context['form'] === 'N/A') {
                        $context['form'] = $v;
                    }
                    if (str_starts_with($v, '#') && (empty($context['form_id']) || $context['form_id'] === 'N/A')) {
                        $context['form_id'] = ltrim($v, '#');
                    }
                }
            }
        }
    }
}
