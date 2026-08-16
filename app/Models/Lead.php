<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\ClientScoped;
use Carbon\Carbon;

class Lead extends Model
{
    use HasFactory, ClientScoped;

    protected $guarded = [];

    protected $casts = [
        'first_response_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(LeadCall::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LeadMessage::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(LeadCommunication::class);
    }

    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    /**
     * Calculate dynamic rule-based lead score (0-100).
     */
    public function calculateScore(): int
    {
        $weights = config('lead_scoring');
        $score = 0;

        // 1. Source Quality (0-25 pts)
        if ($this->leadSource) {
            $sourceName = strtolower($this->leadSource->name ?? '');
            if (str_contains($sourceName, 'portal') || str_contains($sourceName, 'direct')) {
                $score += $weights['source_quality']['portal_direct'] ?? 25;
            } elseif (str_contains($sourceName, 'referral')) {
                $score += $weights['source_quality']['referral'] ?? 25;
            } elseif (str_contains($sourceName, 'website')) {
                $score += $weights['source_quality']['website_form'] ?? 20;
            } elseif (str_contains($sourceName, 'meta') || str_contains($sourceName, 'facebook')) {
                $score += $weights['source_quality']['meta_cold_form'] ?? 10;
            } else {
                $score += $this->leadSource->default_score_weight ?? ($weights['source_quality']['default_weight'] ?? 15);
            }
        } else {
            $score += $weights['source_quality']['default_weight'] ?? 15;
        }

        // 2. Budget vs Project Range (0-25 pts)
        if ($this->budget) {
            $budgetVal = (float) $this->budget;
            if ($budgetVal >= 5000000) {
                $score += $weights['budget_match']['exact_range_points'] ?? 25;
            } elseif ($budgetVal >= 3000000) {
                $score += $weights['budget_match']['partial_range_points'] ?? 15;
            } else {
                $score += $weights['budget_match']['default_points'] ?? 10;
            }
        } else {
            $score += 10;
        }

        // 3. Engagement Activities (0-50 pts)
        // Outbound Call
        if ($this->calls()->exists()) {
            $score += $weights['engagement']['outbound_call'] ?? 10;
        }

        // Outbound Message
        if ($this->messages()->where('direction', 'outbound')->exists() || $this->communications()->where('direction', 'outbound')->exists()) {
            $score += $weights['engagement']['outbound_message_reply'] ?? 10;
        }

        // Attended Site Visit
        if ($this->siteVisits()->exists()) {
            $score += $weights['engagement']['site_visit_attended'] ?? 20;
        }

        // Detailed Requirement Provided
        if (!empty($this->requirement) && strlen($this->requirement) > 5) {
            $score += $weights['engagement']['detailed_requirement'] ?? 10;
        }

        // 4. SLA First Response Speed (0-10 pts)
        if ($this->first_response_at && $this->created_at) {
            $diffInMinutes = Carbon::parse($this->created_at)->diffInMinutes(Carbon::parse($this->first_response_at));
            if ($diffInMinutes <= 15) {
                $score += $weights['sla_speed']['under_15_min'] ?? 10;
            } elseif ($diffInMinutes <= 30) {
                $score += $weights['sla_speed']['under_30_min'] ?? 5;
            }
        }

        $maxScore = $weights['max_score'] ?? 100;
        return (int) min($maxScore, max(0, $score));
    }
}
