<?php

namespace App\Support\LeadMappers;

use Illuminate\Support\Str;

class MetaLeadMapper
{
    /**
     * Clean and beautify raw Meta Lead Ads choice values and question answers.
     * Handles slugified choices like '3_bhk_', 'yes,_i_want_a_site_visit',
     * '3–6_months', '☎️_12_noon_to_4pm', 'only_want_details_on_whatsapp', etc.
     */
    public static function cleanMetaValue(mixed $val): string
    {
        if (is_array($val)) {
            $parts = array_map(fn($v) => self::cleanMetaValue($v), $val);
            return implode(', ', array_filter($parts, fn($p) => $p !== ''));
        }

        if (!is_string($val)) {
            return (string) ($val ?? '');
        }

        $str = trim($val);
        if ($str === '') {
            return '';
        }

        // Strip leading/trailing underscores and surrounding whitespace
        $cleaned = trim($str, "_\t\n\r\0\x0B ");

        // Replace internal underscores with spaces
        $cleaned = str_replace('_', ' ', $cleaned);

        // Normalize multiple spaces into a single space
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        // Normalize punctuation spacing (commas, hyphens)
        $cleaned = preg_replace('/\s*,\s*/', ', ', $cleaned);
        $cleaned = preg_replace('/\s*-\s*/', ' - ', $cleaned);

        // Normalize lowercase snake_case strings into Title Case words (e.g. 'noida_expressway' -> 'Noida Expressway')
        if (!str_contains($cleaned, '@')) {
            if (preg_match('/[a-z]/', $cleaned)) {
                if (!preg_match('/[A-Z]/', $str)) {
                    $cleaned = ucwords($cleaned, " \t\r\n\f\v(-–_");
                } elseif (preg_match('/^[a-z]/', $cleaned)) {
                    $cleaned = ucfirst($cleaned);
                }
            }
        }

        // Real Estate & Meta Ads Acronym and Word Beautification
        $replacements = [
            '/\bbhk\b/i' => 'BHK',
            '/\brk\b/i' => 'RK',
            '/\bcr\b/i' => 'Cr',
            '/\bcrore\b/i' => 'Crore',
            '/\blakhs?\b/i' => 'Lakh',
            '/\blacs?\b/i' => 'Lakh',
            '/\bncr\b/i' => 'NCR',
            '/\bnri\b/i' => 'NRI',
            '/\bwhatsapp\b/i' => 'WhatsApp',
            '/\bi\b/' => 'I',
            '/\bsqft\b/i' => 'Sq. Ft.',
            '/\bsq\.ft\b/i' => 'Sq. Ft.',
            '/\b(\d+)\s*pm\b/i' => '$1 PM',
            '/\b(\d+)\s*am\b/i' => '$1 AM',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $cleaned = preg_replace($pattern, $replacement, $cleaned);
        }

        return trim($cleaned);
    }

    /**
     * Map Meta (Facebook & Instagram) Leadgen payload into CRM lead attributes.
     */
    public function map(array $raw): array
    {
        // Extract inner leadgen value if wrapped in Meta webhook entry structure
        $leadVal = $raw['entry'][0]['changes'][0]['value'] ?? $raw;

        $name = $leadVal['full_name'] ?? $leadVal['name'] ?? $raw['full_name'] ?? null;
        $firstName = null;
        $lastName = null;
        $mobile = $leadVal['phone_number'] ?? $leadVal['mobile'] ?? $raw['phone_number'] ?? null;
        $email = $leadVal['email'] ?? $raw['email'] ?? null;
        $city = $leadVal['city'] ?? $raw['city'] ?? null;
        $location = $leadVal['location'] ?? $raw['location'] ?? null;
        $budget = $leadVal['budget'] ?? $raw['budget'] ?? null;
        $propertyType = $leadVal['property_type'] ?? $raw['property_type'] ?? null;

        // Collect all question-answer pairs and custom fields
        $customQuestions = [];
        $allFieldAnswers = [];

        $fieldData = $leadVal['field_data'] ?? $raw['field_data'] ?? null;
        if (is_array($fieldData)) {
            foreach ($fieldData as $field) {
                if (isset($field['name']) && isset($field['values']) && is_array($field['values']) && count($field['values']) > 0) {
                    $rawKey = (string) $field['name'];
                    $cleanVal = self::cleanMetaValue($field['values']);

                    if ($cleanVal === '') {
                        continue;
                    }

                    // Normalize question key for pattern matching
                    $keyNormalized = strtolower(trim(str_replace(['?', ':', '-', '_'], ' ', $rawKey)));

                    // Ignore internal meta parameters like inbox_url or facebook URLs
                    if ($keyNormalized === 'inbox url' || str_contains($keyNormalized, 'inbox url') || str_contains($cleanVal, 'business.facebook.com')) {
                        continue;
                    }

                    $allFieldAnswers[$rawKey] = $cleanVal;

                    // Match Full Name
                    if ($keyNormalized === 'full name' || $keyNormalized === 'name' || str_contains($keyNormalized, 'full name') || str_contains($keyNormalized, 'your name')) {
                        $name = $cleanVal;
                    } elseif ($keyNormalized === 'first name' || str_contains($keyNormalized, 'first name')) {
                        $firstName = $cleanVal;
                    } elseif ($keyNormalized === 'last name' || str_contains($keyNormalized, 'last name')) {
                        $lastName = $cleanVal;
                    }
                    // Match Mobile / Phone Number
                    elseif (str_contains($keyNormalized, 'phone') || str_contains($keyNormalized, 'mobile') || str_contains($keyNormalized, 'contact number') || str_contains($keyNormalized, 'whatsapp number')) {
                        $mobile = $cleanVal;
                    }
                    // Match Email
                    elseif (str_contains($keyNormalized, 'email')) {
                        $email = strtolower(trim($cleanVal));
                    }
                    // Match City
                    elseif (str_contains($keyNormalized, 'city') || str_contains($keyNormalized, 'town') || str_contains($keyNormalized, 'living in') || str_contains($keyNormalized, 'residence')) {
                        $city = $cleanVal;
                        if (!in_array($keyNormalized, ['city', 'town'])) {
                            $customQuestions[$rawKey] = $cleanVal;
                        }
                    }
                    // Match Location / Sector / Locality
                    elseif (str_contains($keyNormalized, 'location') || str_contains($keyNormalized, 'locality') || str_contains($keyNormalized, 'sector') || str_contains($keyNormalized, 'preferred area')) {
                        $location = $cleanVal;
                        if (!in_array($keyNormalized, ['location', 'locality'])) {
                            $customQuestions[$rawKey] = $cleanVal;
                        }
                    }
                    // Match Budget / Price
                    elseif (str_contains($keyNormalized, 'budget') || str_contains($keyNormalized, 'price') || str_contains($keyNormalized, 'investment')) {
                        $budget = $cleanVal;
                        $customQuestions[$rawKey] = $cleanVal;
                    }
                    // Match Property Type / Unit Configuration
                    elseif (str_contains($keyNormalized, 'property') || str_contains($keyNormalized, 'configuration') || str_contains($keyNormalized, 'bhk') || str_contains($keyNormalized, 'flat') || str_contains($keyNormalized, 'unit') || str_contains($keyNormalized, 'space size')) {
                        $isPurpose = preg_match('/\b(rental\s+income|self\s+use|investment|personal\s+use|own\s+use|yes|no)\b/i', $cleanVal);
                        if (!$isPurpose) {
                            $propertyType = $cleanVal;
                        }
                        $customQuestions[$rawKey] = $cleanVal;
                    } else {
                        $customQuestions[$rawKey] = $cleanVal;
                    }
                }
            }
        }

        // Run smart extractor over collected fields for budget, propertyType, location, etc.
        $extracted = \App\Support\LeadPropertyAndBudgetExtractor::extractFromQuestions($allFieldAnswers);
        if (empty($budget) && !empty($extracted['budget'])) {
            $budget = $extracted['budget'];
        }
        if (!empty($extracted['property_type'])) {
            $isOnlySize = preg_match('/^\d+(?:\.\d+)?\s*(?:sq\.?\s*ft\.?|sqft|sq\s*yards?|sq\s*meters?)$/i', trim((string) $propertyType));
            if (empty($propertyType) || $isOnlySize || preg_match('/(?:₹|rs\.?|inr|\blakh|\bcr|\bcrore|\brental\s+income|\bself\s+use|\binvestment|\byes|\bno)\b/i', (string) $propertyType)) {
                $propertyType = $extracted['property_type'];
            }
        }
        if (empty($location) && !empty($extracted['location'])) {
            $location = $extracted['location'];
        }

        // Capture top-level remarks / notes if provided
        $remarks = $leadVal['remarks'] ?? $leadVal['Remarks'] ?? $raw['remarks'] ?? $raw['Remarks'] ?? null;
        if (!empty($remarks) && !isset($customQuestions['Remarks'])) {
            $customQuestions['Remarks'] = self::cleanMetaValue($remarks);
        }

        // Combine first and last name if full_name wasn't explicitly provided
        if (empty($name) && ($firstName || $lastName)) {
            $name = trim("{$firstName} {$lastName}");
        }

        // Clean mobile number (keep digits only)
        $cleanMobile = $mobile ? preg_replace('/[^0-9]/', '', (string) $mobile) : '';

        // If name is not provided, do not use fake human names like 'Meta Lead User'
        if (empty($name)) {
            $leadId = $leadVal['id'] ?? $leadVal['leadgen_id'] ?? $raw['id'] ?? $raw['leadgen_id'] ?? null;
            $name = $cleanMobile ? "Lead {$cleanMobile}" : ($leadId ? "Lead #{$leadId}" : 'Meta Lead');
        }

        // Clean email (store null if missing or invalid)
        $cleanEmail = $email ? strtolower(trim($email)) : null;
        if ($cleanEmail === '' || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            $cleanEmail = null;
        }

        // Clean budget and property type if they came from top-level raw fields
        if ($propertyType) {
            $propertyType = self::cleanMetaValue($propertyType);
        }
        if ($city) {
            $city = self::cleanMetaValue($city);
        }
        if ($location) {
            $location = self::cleanMetaValue($location);
        }

        // Campaign, AdSet, Ad, and Form Details
        $campaignName = $leadVal['campaign_name'] ?? $raw['campaign_name'] ?? null;
        $campaignId = $leadVal['campaign_id'] ?? $raw['campaign_id'] ?? null;
        $adsetName = $leadVal['adset_name'] ?? $raw['adset_name'] ?? null;
        $adsetId = $leadVal['adset_id'] ?? $raw['adset_id'] ?? null;
        $adName = $leadVal['ad_name'] ?? $raw['ad_name'] ?? null;
        $adId = $leadVal['ad_id'] ?? $raw['ad_id'] ?? null;
        $formId = $leadVal['form_id'] ?? $raw['form_id'] ?? null;
        $formName = $leadVal['form_name'] ?? $raw['form_name'] ?? null;
        $pageId = $leadVal['page_id'] ?? $raw['page_id'] ?? ($raw['entry'][0]['id'] ?? null);
        $leadgenId = $leadVal['id'] ?? $leadVal['leadgen_id'] ?? $raw['id'] ?? $raw['leadgen_id'] ?? null;
        $createdTime = $leadVal['created_time'] ?? $raw['created_time'] ?? null;
        $platform = $leadVal['platform'] ?? $raw['platform'] ?? null;
        $isOrganic = $leadVal['is_organic'] ?? $raw['is_organic'] ?? null;

        // Build a detailed human-readable requirement text preserving all form responses
        $reqParts = [];
        if ($propertyType) {
            $reqParts[] = "Property: {$propertyType}";
        }
        if ($budget) {
            $reqParts[] = "Budget: {$budget}";
        }
        if ($location) {
            $reqParts[] = "Location: {$location}";
        }
        if ($city) {
            $reqParts[] = "City: {$city}";
        }

        foreach ($customQuestions as $q => $a) {
            $cleanQ = trim($q, "?:!-_ \t\n\r\0\x0B");
            $readableQ = ucwords(str_replace(['_', '-'], ' ', $cleanQ));
            $lowerQ = strtolower($readableQ);
            // Avoid duplicate lines if already added as primary attributes
            if ($city && in_array($lowerQ, ['city', 'town']) && strcasecmp($a, $city) === 0) {
                continue;
            }
            if ($location && in_array($lowerQ, ['location', 'locality']) && strcasecmp($a, $location) === 0) {
                continue;
            }
            $reqParts[] = "{$readableQ}: {$a}";
        }

        $metaAttribution = [];
        if ($campaignName) $metaAttribution[] = "Campaign: {$campaignName}";
        if ($adsetName) $metaAttribution[] = "AdSet: {$adsetName}";
        if ($adName) $metaAttribution[] = "Ad: {$adName}";
        if ($formName) $metaAttribution[] = "Form: {$formName}";
        elseif ($formId) $metaAttribution[] = "Form: #{$formId}";

        if (!empty($metaAttribution)) {
            $reqParts[] = "Meta Context: " . implode(' | ', $metaAttribution);
        }

        $requirement = !empty($reqParts) 
            ? implode("\n• ", array_merge(['Meta Lead Submission:'], $reqParts))
            : ($formId ? "Meta Lead Ingestion from Form #{$formId}" : "Meta Lead Submission");

        return [
            'name' => $name,
            'mobile' => $cleanMobile,
            'email' => $cleanEmail,
            'city' => $city ?: null,
            'location' => $location ?: null,
            'budget' => $budget ?: null,
            'property_type' => $propertyType ?: null,
            'requirement' => $requirement,
            'source' => 'meta',
            'extracted_details' => $extracted ?? [],
            'campaign' => $campaignName,
            'campaign_id' => $campaignId,
            'ad_set' => $adsetName,
            'ad_set_id' => $adsetId,
            'ad' => $adName,
            'ad_id' => $adId,
            'form' => $formId,
            'form_name' => $formName,
            'page_id' => $pageId,
            'created_time' => $createdTime,
            'platform' => $platform,
            'is_organic' => $isOrganic,
            'leadgen_id' => $leadgenId,
            'custom_fields' => $allFieldAnswers,
            'raw_utm' => [
                'utm_source' => $platform && strtolower((string) $platform) === 'ig' ? 'instagram' : 'facebook',
                'utm_medium' => 'cpc',
                'utm_campaign' => $campaignName ?: null,
                'utm_content' => $adName ?: null,
            ],
        ];
    }
}


