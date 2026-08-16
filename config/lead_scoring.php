<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead Scoring Weights & Rule Definitions
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for computing dynamic lead_score (0-100).
    | Executive priorities are calculated based on Source Quality, Budget Match,
    | Real-Time Engagement, and SLA First Response Speed.
    |
    */

    'max_score' => 100,

    'source_quality' => [
        'default_weight' => 15,
        'portal_direct' => 25,
        'website_form' => 20,
        'referral' => 25,
        'meta_cold_form' => 10,
    ],

    'budget_match' => [
        'exact_range_points' => 25,
        'partial_range_points' => 15,
        'default_points' => 10,
    ],

    'engagement' => [
        'outbound_call' => 10,
        'outbound_message_reply' => 10,
        'site_visit_attended' => 20,
        'detailed_requirement' => 10,
    ],

    'sla_speed' => [
        'under_15_min' => 10,
        'under_30_min' => 5,
        'breached' => 0,
    ],
];
