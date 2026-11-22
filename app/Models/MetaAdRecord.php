<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdRecord extends Model
{
    protected $guarded = [];
    protected $casts = [
        'data' => 'array',
        'lead_fetch_state' => 'array',
        'synced_at' => 'datetime',
        'report_date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function crmCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'crm_campaign_id');
    }

    public function campaignRecord(): BelongsTo
    {
        return $this->belongsTo(MetaAdRecord::class, 'campaign_id', 'remote_id')
            ->where('meta_ad_account_id', $this->meta_ad_account_id)
            ->where('kind', 'campaign');
    }

    public function adsetRecord(): BelongsTo
    {
        return $this->belongsTo(MetaAdRecord::class, 'adset_id', 'remote_id')
            ->where('meta_ad_account_id', $this->meta_ad_account_id)
            ->where('kind', 'adset');
    }

    public function leadForm(): BelongsTo
    {
        return $this->belongsTo(LeadFormMapping::class, 'lead_form_id', 'form_id');
    }

    public function getEffectiveStatusAttribute(): ?string
    {
        return $this->attributes['effective_status'] ?? data_get($this->data, 'effective_status') ?? $this->status;
    }

    public function getObjectiveAttribute(): ?string
    {
        return data_get($this->data, 'objective');
    }

    public function getBuyingTypeAttribute(): ?string
    {
        return data_get($this->data, 'buying_type');
    }

    public function getOptimizationGoalAttribute(): ?string
    {
        return data_get($this->data, 'optimization_goal');
    }

    public function getBidStrategyAttribute(): ?string
    {
        return data_get($this->data, 'bid_strategy');
    }

    public function getDailyBudgetAttribute(): ?string
    {
        $val = data_get($this->data, 'daily_budget');
        return $val !== null ? (string) ($val / 100) : null;
    }

    public function getLifetimeBudgetAttribute(): ?string
    {
        $val = data_get($this->data, 'lifetime_budget');
        return $val !== null ? (string) ($val / 100) : null;
    }

    public function getBudgetDisplayAttribute(): string
    {
        if ($this->daily_budget !== null && (float) $this->daily_budget > 0) {
            return 'Daily: ₹' . number_format((float) $this->daily_budget, 2);
        }
        if ($this->lifetime_budget !== null && (float) $this->lifetime_budget > 0) {
            return 'Lifetime: ₹' . number_format((float) $this->lifetime_budget, 2);
        }
        return '—';
    }

    public function getCreativeThumbnailAttribute(): ?string
    {
        return data_get($this->data, 'creative.thumbnail_url')
            ?? data_get($this->data, 'creative.image_url')
            ?? data_get($this->data, 'creative.object_story_spec.link_data.picture')
            ?? data_get($this->data, 'creative.object_story_spec.video_data.image_url');
    }

    public function getCreativeTitleAttribute(): ?string
    {
        return data_get($this->data, 'creative.title')
            ?? data_get($this->data, 'creative.object_story_spec.link_data.name')
            ?? data_get($this->data, 'creative.object_story_spec.video_data.title')
            ?? data_get($this->data, 'creative.name');
    }

    public function getCreativeBodyAttribute(): ?string
    {
        return data_get($this->data, 'creative.body')
            ?? data_get($this->data, 'creative.object_story_spec.link_data.message')
            ?? data_get($this->data, 'creative.object_story_spec.video_data.message');
    }

    public function getDestinationUrlAttribute(): ?string
    {
        return data_get($this->data, 'creative.object_story_spec.link_data.link')
            ?? data_get($this->data, 'preview_shareable_link');
    }

    public function getImpressionsAttribute(): int
    {
        return (int) data_get($this->data, 'impressions', 0);
    }

    public function getReachAttribute(): int
    {
        return (int) data_get($this->data, 'reach', 0);
    }

    public function getClicksAttribute(): int
    {
        return (int) data_get($this->data, 'clicks', 0);
    }

    public function getLinkClicksAttribute(): int
    {
        return (int) (data_get($this->data, 'inline_link_clicks') ?? data_get($this->data, 'clicks', 0));
    }

    public function getSpendAttribute(): float
    {
        return (float) data_get($this->data, 'spend', 0);
    }

    public function getCpmAttribute(): float
    {
        $cpm = data_get($this->data, 'cpm');
        if ($cpm !== null) return (float) $cpm;
        return $this->impressions > 0 ? round(($this->spend / $this->impressions) * 1000, 2) : 0.0;
    }

    public function getCpcAttribute(): float
    {
        $cpc = data_get($this->data, 'cpc');
        if ($cpc !== null) return (float) $cpc;
        return $this->clicks > 0 ? round($this->spend / $this->clicks, 2) : 0.0;
    }

    public function getCtrAttribute(): float
    {
        $ctr = data_get($this->data, 'ctr');
        if ($ctr !== null) return (float) $ctr;
        return $this->impressions > 0 ? round(($this->clicks / $this->impressions) * 100, 2) : 0.0;
    }

    public function getFrequencyAttribute(): float
    {
        $freq = data_get($this->data, 'frequency');
        if ($freq !== null) return (float) $freq;
        return $this->reach > 0 ? round($this->impressions / $this->reach, 2) : 1.0;
    }

    public function getLeadsCountAttribute(): int
    {
        if (isset($this->data['parsed_leads'])) {
            return (int) $this->data['parsed_leads'];
        }
        $leads = 0;
        foreach ((array) data_get($this->data, 'actions', []) as $act) {
            $type = $act['action_type'] ?? '';
            if (in_array($type, ['lead', 'onsite_conversion.lead_grouped', 'leadgen_grouped'])) {
                $leads = max($leads, (int) ($act['value'] ?? 0));
            }
        }
        return $leads;
    }

    public function getCostPerLeadAttribute(): ?float
    {
        if (isset($this->data['parsed_cost_per_lead'])) {
            return $this->data['parsed_cost_per_lead'] !== null ? (float) $this->data['parsed_cost_per_lead'] : null;
        }
        foreach ((array) data_get($this->data, 'cost_per_action_type', []) as $cpa) {
            $type = $cpa['action_type'] ?? '';
            if (in_array($type, ['lead', 'onsite_conversion.lead_grouped', 'leadgen_grouped'])) {
                return (float) ($cpa['value'] ?? 0);
            }
        }
        return $this->leads_count > 0 ? round($this->spend / $this->leads_count, 2) : null;
    }
}
