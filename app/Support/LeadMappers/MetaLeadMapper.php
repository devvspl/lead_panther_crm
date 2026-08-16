<?php

namespace App\Support\LeadMappers;

class MetaLeadMapper
{
    public function map(array $raw): array
    {
        // Extract inner leadgen value if wrapped in Meta entry structure
        $leadVal = $raw['entry'][0]['changes'][0]['value'] ?? $raw;

        $name = $leadVal['full_name'] ?? $leadVal['name'] ?? $raw['full_name'] ?? 'Meta Lead User';
        $mobile = $leadVal['phone_number'] ?? $leadVal['mobile'] ?? $raw['phone_number'] ?? '9876543210';
        $email = $leadVal['email'] ?? $raw['email'] ?? 'meta_user@example.com';

        $fieldData = $leadVal['field_data'] ?? $raw['field_data'] ?? null;
        if (is_array($fieldData)) {
            foreach ($fieldData as $field) {
                if (isset($field['name']) && isset($field['values'][0])) {
                    $fieldName = strtolower($field['name']);
                    $val = $field['values'][0];
                    if (str_contains($fieldName, 'name')) $name = $val;
                    if (str_contains($fieldName, 'phone') || str_contains($fieldName, 'mobile')) $mobile = $val;
                    if (str_contains($fieldName, 'email')) $email = $val;
                }
            }
        }

        return [
            'name' => $name,
            'mobile' => preg_replace('/[^0-9]/', '', $mobile),
            'email' => $email,
            'city' => $leadVal['city'] ?? $raw['city'] ?? 'Mumbai',
            'budget' => $leadVal['budget'] ?? $raw['budget'] ?? '₹75.0L - ₹1.2Cr',
            'property_type' => $leadVal['property_type'] ?? $raw['property_type'] ?? '3 BHK Apartment',
            'requirement' => $leadVal['requirement'] ?? $raw['requirement'] ?? 'Interested in Luxury 3 BHK Project via Meta Ad',
            'source' => 'meta',
            'campaign' => $leadVal['campaign_name'] ?? $raw['campaign_name'] ?? 'Meta Spring Campaign',
            'ad_set' => $leadVal['adset_name'] ?? $raw['adset_name'] ?? 'Meta High-Net-Worth Target',
            'ad' => $leadVal['ad_name'] ?? $raw['ad_name'] ?? 'Meta Carousel Ad #1',
            'form' => $leadVal['form_id'] ?? $raw['form_id'] ?? 'meta_form_9988',
            'raw_utm' => [
                'utm_source' => 'facebook',
                'utm_medium' => 'cpc',
                'utm_campaign' => $leadVal['campaign_name'] ?? $raw['campaign_name'] ?? 'Meta Spring Campaign',
            ]
        ];
    }
}
