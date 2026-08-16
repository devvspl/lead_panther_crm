<?php

namespace App\Support\LeadMappers;

class OwnedPortalLeadMapper
{
    public function map(array $raw): array
    {
        return [
            'name' => $raw['buyer_name'] ?? $raw['name'] ?? 'Direct Website Prospect',
            'mobile' => preg_replace('/[^0-9]/', '', $raw['contact_number'] ?? $raw['mobile'] ?? '9876543213'),
            'email' => $raw['email_address'] ?? $raw['email'] ?? 'direct_user@example.com',
            'city' => $raw['city'] ?? 'Dholera',
            'budget' => $raw['budget_bracket'] ?? '₹25.0L - ₹45.0L',
            'property_type' => $raw['plot_type'] ?? 'Residential Plot 1500 sqft',
            'requirement' => $raw['message'] ?? 'Direct web form enquiry on Dholera Smart City Portal',
            'source' => 'owned',
            'campaign' => $raw['utm_campaign'] ?? 'Dholera Smart City Direct Organic',
            'ad_set' => $raw['utm_content'] ?? 'Header Form',
            'ad' => $raw['utm_term'] ?? 'smart city plot',
            'form' => 'owned_landing_page_form',
            'raw_utm' => [
                'utm_source' => $raw['utm_source'] ?? 'dholera_portal',
                'utm_medium' => $raw['utm_medium'] ?? 'organic',
                'utm_campaign' => $raw['utm_campaign'] ?? 'Direct Web Registration',
            ]
        ];
    }
}
