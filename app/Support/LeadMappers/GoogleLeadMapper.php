<?php

namespace App\Support\LeadMappers;

class GoogleLeadMapper
{
    public function map(array $raw): array
    {
        $userColumnData = $raw['user_column_data'] ?? $raw;
        $name = $raw['lead_name'] ?? 'Google Search User';
        $mobile = $raw['phone_number'] ?? '9876543211';
        $email = $raw['email'] ?? 'google_user@example.com';

        if (is_array($userColumnData)) {
            foreach ($userColumnData as $col) {
                if (isset($col['column_id']) && isset($col['string_value'])) {
                    $id = strtolower($col['column_id']);
                    $val = $col['string_value'];
                    if (str_contains($id, 'full_name') || str_contains($id, 'name')) $name = $val;
                    if (str_contains($id, 'phone_number') || str_contains($id, 'phone')) $mobile = $val;
                    if (str_contains($id, 'email')) $email = $val;
                }
            }
        }

        return [
            'name' => $name,
            'mobile' => preg_replace('/[^0-9]/', '', $mobile),
            'email' => $email,
            'city' => $raw['city'] ?? 'Pune',
            'budget' => $raw['budget'] ?? '₹50.0L - ₹85.0L',
            'property_type' => $raw['property_type'] ?? '2 BHK Apartment',
            'requirement' => $raw['requirement'] ?? 'Interested via Google Search Ads',
            'source' => 'google',
            'campaign' => $raw['campaign_id'] ?? 'Google Search High Intent',
            'ad_set' => $raw['ad_group_id'] ?? 'Google Keywords Group 1',
            'ad' => $raw['creative_id'] ?? 'Search Text Creative A',
            'form' => $raw['form_id'] ?? 'google_form_1122',
            'raw_utm' => [
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'Google Search High Intent',
            ]
        ];
    }
}
