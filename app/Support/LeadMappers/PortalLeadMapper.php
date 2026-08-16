<?php

namespace App\Support\LeadMappers;

class PortalLeadMapper
{
    public function map(array $raw): array
    {
        return [
            'name' => $raw['LeadName'] ?? $raw['name'] ?? 'Housing Portal Buyer',
            'mobile' => preg_replace('/[^0-9]/', '', $raw['MobileNumber'] ?? $raw['mobile'] ?? '9876543212'),
            'email' => $raw['EmailId'] ?? $raw['email'] ?? 'portal_user@example.com',
            'city' => $raw['City'] ?? 'Bengaluru',
            'budget' => $raw['BudgetRange'] ?? '₹1.0Cr - ₹1.8Cr',
            'property_type' => $raw['PropertyType'] ?? '3 BHK Villa',
            'requirement' => $raw['QueryDetails'] ?? 'Enquired on 99acres/MagicBricks listing',
            'source' => 'portal',
            'campaign' => $raw['PortalName'] ?? '99acres Featured Listing',
            'ad_set' => $raw['ProjectListingId'] ?? 'Listing #445566',
            'ad' => $raw['BannerType'] ?? 'Top Listing Banner',
            'form' => 'portal_lead_form',
            'raw_utm' => [
                'utm_source' => strtolower($raw['PortalName'] ?? 'housing_portal'),
                'utm_medium' => 'referral',
                'utm_campaign' => 'Portal Property Enquiry',
            ]
        ];
    }
}
