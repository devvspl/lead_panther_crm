<?php

namespace App\Traits;

trait HasLeadCreditCost
{
    public function initializeHasLeadCreditCost(): void
    {
        $this->mergeCasts(['lead_cost' => 'integer']);
        $this->makeHidden(['legacy_lead_cost', 'legacy_min_credit_threshold', 'credit_delivery_confirmed']);
    }
    public static function bootHasLeadCreditCost(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('lead_cost') && $model->getAttributes()['lead_cost'] !== null) {
                $model->lead_cost = \App\Support\LeadCredits::quantity($model->getAttributes()['lead_cost'], 'lead_cost', 1);
            }
        });
    }
}
