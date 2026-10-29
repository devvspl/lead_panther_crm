<?php

namespace App\Support\LeadMappers;

use Illuminate\Support\Str;

class MetaLeadMapper
{
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
                    $rawKey = $field['name'];
                    $keyLower = strtolower($rawKey);
                    $val = trim((string) $field['values'][0]);

                    if ($val === '') {
                        continue;
                    }

                    $allFieldAnswers[$rawKey] = $val;

                    // Match standard and common custom lead fields
                    if ($keyLower === 'full_name' || $keyLower === 'name') {
                        $name = $val;
                    } elseif ($keyLower === 'first_name') {
                        $firstName = $val;
                    } elseif ($keyLower === 'last_name') {
                        $lastName = $val;
                    } elseif (str_contains($keyLower, 'phone') || str_contains($keyLower, 'mobile') || str_contains($keyLower, 'contact')) {
                        $mobile = $val;
                    } elseif (str_contains($keyLower, 'email')) {
                        $email = $val;
                    } elseif (str_contains($keyLower, 'city') || str_contains($keyLower, 'town')) {
                        $city = $val;
                    } elseif (str_contains($keyLower, 'location') || str_contains($keyLower, 'locality') || str_contains($keyLower, 'area')) {
                        $location = $val;
                    } elseif (str_contains($keyLower, 'budget') || str_contains($keyLower, 'price') || str_contains($keyLower, 'investment')) {
                        $budget = $val;
                        $customQuestions[$rawKey] = $val;
                    } elseif (str_contains($keyLower, 'property') || str_contains($keyLower, 'configuration') || str_contains($keyLower, 'bhk') || str_contains($keyLower, 'flat') || str_contains($keyLower, 'unit')) {
                        $propertyType = $val;
                        $customQuestions[$rawKey] = $val;
                    } else {
                        $customQuestions[$rawKey] = $val;
                    }
                }
            }
        }

        // Combine first and last name if full_name wasn't explicitly provided
        if (empty($name) && ($firstName || $lastName)) {
            $name = trim("{$firstName} {$lastName}");
        }

        if (empty($name)) {
            $name = 'Meta Lead User';
        }

        // Clean mobile number (keep digits only)
        $cleanMobile = $mobile ? preg_replace('/[^0-9]/', '', (string) $mobile) : '9876543210';
        if (strlen($cleanMobile) === 0) {
            $cleanMobile = '9876543210';
        }

        // Campaign, AdSet, Ad, and Form Details
        $campaignName = $leadVal['campaign_name'] ?? $raw['campaign_name'] ?? null;
        $campaignId = $leadVal['campaign_id'] ?? $raw['campaign_id'] ?? null;
        $adsetName = $leadVal['adset_name'] ?? $raw['adset_name'] ?? null;
        $adsetId = $leadVal['adset_id'] ?? $raw['adset_id'] ?? null;
        $adName = $leadVal['ad_name'] ?? $raw['ad_name'] ?? null;
        $adId = $leadVal['ad_id'] ?? $raw['ad_id'] ?? null;
        $formId = $leadVal['form_id'] ?? $raw['form_id'] ?? null;
        $leadgenId = $leadVal['id'] ?? $leadVal['leadgen_id'] ?? $raw['id'] ?? $raw['leadgen_id'] ?? null;

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
            $readableQ = ucwords(str_replace(['_', '-'], ' ', $q));
            $reqParts[] = "{$readableQ}: {$a}";
        }

        $metaAttribution = [];
        if ($campaignName) $metaAttribution[] = "Campaign: {$campaignName}";
        if ($adsetName) $metaAttribution[] = "AdSet: {$adsetName}";
        if ($adName) $metaAttribution[] = "Ad: {$adName}";
        if ($formId) $metaAttribution[] = "Form: #{$formId}";

        if (!empty($metaAttribution)) {
            $reqParts[] = "Meta Context: " . implode(' | ', $metaAttribution);
        }

        $requirement = !empty($reqParts) 
            ? implode("\n• ", array_merge(['Meta Lead Submission:'], $reqParts))
            : "Meta Lead Ingestion from Form #{$formId}";

        return [
            'name' => $name,
            'mobile' => $cleanMobile,
            'email' => $email ?: 'meta_user@example.com',
            'city' => $city ?: 'Mumbai',
            'location' => $location ?: null,
            'budget' => $budget ?: '7500000',
            'property_type' => $propertyType ?: '2 BHK',
            'requirement' => $requirement,
            'source' => 'meta',
            'campaign' => $campaignName,
            'campaign_id' => $campaignId,
            'ad_set' => $adsetName,
            'ad_set_id' => $adsetId,
            'ad' => $adName,
            'ad_id' => $adId,
            'form' => $formId,
            'leadgen_id' => $leadgenId,
            'custom_fields' => $allFieldAnswers,
            'raw_utm' => [
                'utm_source' => 'facebook',
                'utm_medium' => 'cpc',
                'utm_campaign' => $campaignName ?: 'Meta Spring Campaign',
                'utm_content' => $adName,
            ],
        ];
    }
}

