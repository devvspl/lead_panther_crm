<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\ClientScoped;
use Carbon\Carbon;
use App\Models\Scopes\ClientScope;
use App\Support\NavigationConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Lead extends Model
{
    use \App\Traits\HidesArchivedLeads;

    use ClientScoped, HasFactory;

    use \App\Traits\HasLeadCreditCost;
    protected $guarded = [];
    // Transient service context: the assignment observer has already written the inbox alert.
    public bool $webAssignmentRecorded = false;

    public function toArray(): array
    {
        $data = parent::toArray();
        if (! \App\Support\LeadSourceVisibility::allowed()) {
            $data = \App\Support\LeadSourceVisibility::redact($data);
            if (isset($data['requirement'])) $data['requirement'] = \App\Support\ClientFacingText::label($data['requirement']);
        }
        return \App\Support\FinancialVisibility::allowed() ? $data : \App\Support\FinancialVisibility::redact($data);
    }

    protected $casts = [
        'archived_at' => 'datetime',
        'archive_assignment_hold' => 'boolean',
        'first_response_at' => 'datetime',
        'executive_assigned_at' => 'datetime',
        'executive_response_due_at' => 'datetime',
        'executive_first_response_at' => 'datetime',
        'delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_pending' => 'boolean',
        'internal_processing_pending' => 'boolean',
        'delivery_config' => 'array',
        'delivery_failed_at' => 'datetime',
        'do_not_send_to_client' => 'boolean',
        'delivery_revoked_at' => 'datetime',
    ];

    public static function usesSalesPanel(): bool
    {
        return in_array(NavigationConfig::getRoleKey(auth()->user()), ['builder', 'sales-executive', 'team-leader']);
    }

    // Keep delivery slot allocation and insertion under the same project lock.
    protected function performInsert(Builder $query)
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => parent::performInsert($query));
    }

    /** Owner IDs refer to users. Organization IDs must never be compared to client_id. */
    public static function panelQuery(?User $user = null): Builder
    {
        $query = static::withoutGlobalScope(ClientScope::class);
        $user ??= auth()->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }
        $role = NavigationConfig::getRoleKey($user);
        if ($role === 'team-leader') return \App\Support\TeamAccess::scopeLeads($query, $user);
        if (! $user->hasAnyRole(['Super Admin', 'super-admin'])) $query->where('internal_processing_pending', false);
        if (in_array($role, ['builder', 'sales-executive'])) {
            $query->where('delivery_pending', false);
        }
        if ($role === 'sales-executive') {
            return $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('followups', fn ($fq) => $fq->where('assigned_to', $user->id));
            });
        }
        if ($role === 'builder') {
            $orgUserIds = $user->organization_id
                ? User::where('organization_id', $user->organization_id)->pluck('id')->all()
                : [$user->id];

            return $query->where(function ($q) use ($user, $orgUserIds) {
                // 1. Leads explicitly delivered to this builder or their organization via LeadDeliveryItem
                $q->whereHas('deliveryItems', fn ($di) => $di->whereIn('client_id', $orgUserIds)->whereIn('status', ['sent', 'accepted']))
                  // 2. Leads assigned to the builder or team members in their organization
                  ->orWhereIn('assigned_to', $orgUserIds)
                  // 3. Leads directly confirmed and delivered to this builder/organization
                  // (excluding unallocated pool leads and leads delivered to other clients)
                  ->orWhere(function ($sub) use ($orgUserIds) {
                      $sub->whereIn('client_id', $orgUserIds)
                          ->whereNotNull('delivered_at')
                          ->where('credit_delivery_confirmed', true)
                          ->where('do_not_send_to_client', false)
                          ->whereDoesntHave('deliveryItems', fn ($di) => $di->whereNotIn('client_id', $orgUserIds)->whereIn('status', ['sent', 'accepted']));
                  });
            });
        }

        return $user->hasRole('Super Admin') ? $query : static::query();
    }

    public static function normalizeStage(string $stage): string
    {
        $stage = strtolower(str_replace([' ', '-'], '_', trim($stage)));

        return match ($stage) {
            'lead_received' => 'new', 'connected', 'qualified' => 'contacted',
            'followup' => 'follow_up', 'meeting' => 'meeting_scheduled', 'proposal' => 'negotiation',
            'payment' => 'payment_received', 'won' => 'closed_won', 'closed_lost', 'dead', 'rejected' => 'lost',
            'wrong_number' => 'invalid', default => $stage,
        };
    }

    public static function stageValues(string $stage): array
    {
        $aliases = ['new' => ['lead_received'], 'contacted' => ['connected', 'qualified'], 'follow_up' => ['follow-up', 'followup'], 'meeting_scheduled' => ['meeting', 'Meeting'], 'site_visit' => ['Site Visit'], 'negotiation' => ['proposal', 'Proposal'], 'booking' => ['Booking'], 'payment_received' => ['payment', 'Payment'], 'closed_won' => ['won', 'Closed Won'], 'lost' => ['closed_lost', 'dead', 'rejected'], 'invalid' => ['wrong_number']];

        return array_unique(array_merge([$stage, str_replace('_', '-', $stage), ucwords(str_replace('_', ' ', $stage))], $aliases[$stage] ?? []));
    }

    public function isTerminalStage(): bool
    {
        return in_array(static::normalizeStage($this->current_stage), ['closed_won', 'lost', 'invalid', 'unreachable', 'replaced']);
    }

    /** The detail forms and Kanban use the same allowed operations. */
    public function permittedSalesActions(): array
    {
        if ($this->isTerminalStage()) {
            return [];
        }
        $actions = match (static::normalizeStage($this->current_stage)) {
            'new', 'assigned', 'contacted' => ['logCallAction', 'markInterestedAction', 'addRemarksAction'],
            'interested' => ['addRemarksAction', 'scheduleFollowupAction'],
            'follow_up' => ['scheduleFollowupAction', 'completeFollowupAction', 'rescheduleFollowupAction', 'cancelFollowupAction', 'scheduleMeetingAction'],
            'meeting_scheduled' => ['scheduleMeetingAction', 'updateMeetingStatusAction', 'saveSiteVisitAction'],
            'site_visit' => ['saveSiteVisitAction', 'saveNegotiationAction'],
            'negotiation' => ['saveNegotiationAction', 'saveBookingAction'],
            'booking' => ['saveBookingAction', 'recordPaymentAction'],
            'payment_received' => ['recordPaymentAction', 'uploadPaymentProofAction', 'markClosedWonAction'],
            default => [],
        };

        return $actions ? array_merge($actions, ['markLostAction', 'markInvalidAction']) : [];
    }

    public static function generateUniqueLeadCode(string $prefix = 'LP'): string
    {
        $year = date('Y');
        $maxId = (int) \Illuminate\Support\Facades\DB::table('leads')->max('id');

        do {
            $maxId++;
            $code = sprintf('%s-%s-%08d', $prefix, $year, $maxId);
        } while (\Illuminate\Support\Facades\DB::table('leads')->where('lead_code', $code)->exists());

        return $code;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
    public function internalProcessing() { return $this->hasOne(InternalLeadProcessing::class); }

    public function project(): HasOneThrough
    {
        return $this->hasOneThrough(Project::class, Campaign::class, 'id', 'id', 'campaign_id', 'project_id');
    }

    public function getProjectAttribute(): ?Project
    {
        if ($this->relationLoaded('project')) {
            return $this->getRelationValue('project');
        }
        if ($this->relationLoaded('campaign') && $this->campaign) {
            return $this->campaign->project;
        }
        return $this->getRelationValue('project');
    }

    public function getProjectIdAttribute(): ?int
    {
        if ($this->relationLoaded('campaign') && $this->campaign) {
            return $this->campaign->project_id;
        }
        $campaignId = $this->attributes['campaign_id'] ?? null;
        return $campaignId ? Campaign::withoutGlobalScopes()->whereKey($campaignId)->value('project_id') : null;
    }

    public function setProjectIdAttribute($value): void
    {
        if ($value && empty($this->attributes['campaign_id'])) {
            $sourceId = $this->attributes['lead_source_id'] ?? (LeadSource::first()?->id ?? LeadSource::firstOrCreate(['name' => 'direct'])->id);
            $campaign = Campaign::firstOrCreate(
                ['project_id' => $value, 'name' => 'General Campaign'],
                ['lead_source_id' => $sourceId, 'status' => 'active']
            );
            $this->attributes['campaign_id'] = $campaign->id;
        }
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function negotiations(): HasMany
    {
        return $this->hasMany(Negotiation::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(Followup::class);
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(LeadReplacement::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(LeadMetadata::class);
    }

    public function deliveryRevocations(): HasMany
    {
        return $this->hasMany(LeadDeliveryRevocation::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(LeadDeliveryItem::class, 'lead_id');
    }

    public function leadDeliveryItem()
    {
        return $this->hasOne(LeadDeliveryItem::class, 'lead_id')->latestOfMany();
    }

    public function getMetaAttribute(string $key, mixed $default = null): mixed
    {
        return $this->metadata->firstWhere('key', $key)?->value ?? $default;
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
        if ($this->messages()->where('direction', 'outbound')->exists() || $this->communications()->exists()) {
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
            $diffInMinutes = Carbon::parse($this->client_response_start)->diffInMinutes(Carbon::parse($this->first_response_at));
            if ($diffInMinutes <= 15) {
                $score += $weights['sla_speed']['under_15_min'] ?? 10;
            } elseif ($diffInMinutes <= 30) {
                $score += $weights['sla_speed']['under_30_min'] ?? 5;
            }
        }

        $maxScore = $weights['max_score'] ?? 100;
        return (int) min($maxScore, max(0, $score));
    }

    public function getProjectNameAttribute(): string
    {
        if ($this->relationLoaded('project') && $this->project) {
            return $this->project->name;
        }
        if ($this->relationLoaded('campaign') && $this->campaign) {
            return $this->campaign->project?->name ?: 'N/A';
        }
        return $this->project?->name ?: 'N/A';
    }

    public function getCampaignNameAttribute(): string
    {
        return $this->campaign?->name ?: ($this->getMetaAttribute('meta_campaign_name') ?: '-');
    }

    public function getFormNameAttribute(): string
    {
        $metaForm = $this->getMetaAttribute('meta_form_name');
        if ($metaForm) {
            return $metaForm;
        }

        if (!empty($this->requirement) && preg_match("/form '([^']+)'/i", $this->requirement, $matches)) {
            return $matches[1];
        }

        $formId = $this->getMetaAttribute('meta_form_id');
        if ($formId) {
            $mapping = LeadFormMapping::where('form_id', $formId)->first();
            if ($mapping?->form_name) {
                return $mapping->form_name;
            }
        }

        if ($this->campaign_id) {
            $mapping = LeadFormMapping::where('campaign_id', $this->campaign_id)->first();
            if ($mapping?->form_name) {
                return $mapping->form_name;
            }
        }

        return '-';
    }

    public function getLeadSourceNameAttribute(): string
    {
        return $this->leadSource?->name ?: 'Direct';
    }

    public function getActualPropertyTypeAttribute(): ?string
    {
        return \App\Support\LeadPropertyAndBudgetExtractor::resolvePropertyType($this);
    }

    public function getActualBudgetAttribute(): ?float
    {
        return \App\Support\LeadPropertyAndBudgetExtractor::resolveBudget($this);
    }

    public function getActualLocationAttribute(): ?string
    {
        return \App\Support\LeadPropertyAndBudgetExtractor::resolveLocation($this);
    }

    public function getBudgetFormattedAttribute(): string
    {
        if (!\App\Support\FinancialVisibility::allowed()) return '—';
        $actual = $this->actual_budget;
        return $actual ? \App\Support\LeadPropertyAndBudgetExtractor::formatShortBudget($actual) : '—';
    }

    public function getBudgetDisplayAttribute(): string
    {
        if (!\App\Support\FinancialVisibility::allowed()) return '—';
        $actual = $this->actual_budget;
        return \App\Support\LeadPropertyAndBudgetExtractor::formatBudget($actual);
    }

    public function getAssignedToUserAttribute(): array
    {
        return $this->assignedTo ? [$this->assignedTo] : [];
    }

    public function getSlaBadgeAttribute(): string
    {
        if ($this->internal_processing_pending) return 'Internal Processing';
        if (!$this->assigned_to || !$this->executive_response_due_at || $this->delivery_pending) return 'SLA Pending';
        return ($this->executive_first_response_at ?? now())->gt($this->executive_response_due_at) ? 'SLA Breached'
            : ($this->executive_first_response_at ? 'SLA Met' : 'SLA Pending');
    }

    public function getExecutiveResponseTimeAttribute(): ?float
    {
        return $this->executive_assigned_at && $this->executive_first_response_at
            ? round($this->executive_assigned_at->diffInSeconds($this->executive_first_response_at) / 60, 2) : null;
    }

    /**
     * Human-readable first response timing or elapsed waiting time.
     * Examples: '5 min', '25 min', '1 hr 20 min', 'Waiting 45 min', 'Not Responded'
     */
    public function getFirstResponseTimingAttribute(): string
    {
        $start = $this->client_response_start ?: $this->created_at;
        $responseAt = $this->first_response_at ?: $this->executive_first_response_at;

        if ($responseAt && $start) {
            $seconds = max(0, $start->diffInSeconds($responseAt));
            $minutes = (int) round($seconds / 60);

            return static::formatHumanTiming($minutes);
        }

        if (! $start || $this->internal_processing_pending || $this->delivery_pending) {
            return 'Not Responded';
        }

        if ($this->isTerminalStage() && ! $responseAt) {
            return 'Not Responded';
        }

        $elapsedSeconds = max(0, $start->diffInSeconds(now()));
        $elapsedMinutes = (int) round($elapsedSeconds / 60);

        return 'Waiting ' . static::formatHumanTiming($elapsedMinutes);
    }

    public function getFirstResponseTimeAttribute(): string
    {
        return $this->first_response_timing;
    }

    /**
     * Formats minutes into human-readable timing (e.g. '5 min', '25 min', '1 hr 20 min').
     */
    public static function formatHumanTiming(int $minutes): string
    {
        if ($minutes < 1) {
            return '< 1 min';
        }
        if ($minutes < 60) {
            return "{$minutes} min";
        }
        $hours = (int) floor($minutes / 60);
        $remMinutes = $minutes % 60;
        if ($hours < 24) {
            return $remMinutes > 0 ? "{$hours} hr {$remMinutes} min" : "{$hours} hr";
        }
        $days = (int) floor($hours / 24);
        $remHours = $hours % 24;

        return $remHours > 0 ? "{$days}d {$remHours}h" : "{$days}d";
    }

    /**
     * Determine SLA compliance status: 'met', 'breached', 'pending', or 'neutral'.
     */
    public function getFirstResponseSlaStatusAttribute(): string
    {
        if ($this->internal_processing_pending || $this->delivery_pending) {
            return 'neutral';
        }

        $start = $this->client_response_start ?: $this->created_at;
        $responseAt = $this->first_response_at ?: $this->executive_first_response_at;

        if ($this->isTerminalStage() && ! $responseAt) {
            return 'neutral';
        }

        // When executive SLA deadline is configured, honor existing SLA calculation
        if ($this->assigned_to && $this->executive_response_due_at) {
            if ($responseAt) {
                return $responseAt->gt($this->executive_response_due_at) ? 'breached' : 'met';
            }

            return now()->gt($this->executive_response_due_at) ? 'breached' : 'pending';
        }

        // When no executive deadline is set, use organization threshold (default 15 minutes)
        $thresholdMinutes = 15;
        $org = $this->organization;
        if ($org && isset($org->executive_response_minutes)) {
            $thresholdMinutes = (int) $org->executive_response_minutes;
        }

        if ($responseAt && $start) {
            $diffMinutes = $start->diffInMinutes($responseAt);

            return $diffMinutes <= $thresholdMinutes ? 'met' : 'breached';
        }

        if ($start) {
            $elapsedMinutes = $start->diffInMinutes(now());

            return $elapsedMinutes > $thresholdMinutes ? 'breached' : 'pending';
        }

        return 'neutral';
    }

    /**
     * SLA badge CSS class for first response timing display.
     */
    public function getFirstResponseTimingClassAttribute(): string
    {
        $status = $this->first_response_sla_status;

        return match ($status) {
            'met' => 'bg-emerald-50 text-emerald-800 border border-emerald-300',
            'breached' => 'bg-red-50 text-red-800 border border-red-300',
            'pending' => 'bg-amber-50 text-amber-800 border border-amber-300',
            default => 'bg-neutral-100 text-neutral-800 border border-neutral-300',
        };
    }

    /**
     * Informative tooltip for first response SLA display.
     */
    public function getFirstResponseSlaTooltipAttribute(): string
    {
        $status = $this->first_response_sla_status;
        $timing = $this->first_response_timing;

        return match ($status) {
            'met' => "First response in {$timing} (SLA Met)",
            'breached' => $this->first_response_at || $this->executive_first_response_at
                ? "First response in {$timing} (SLA Breached)"
                : "Overdue! {$timing} elapsed (SLA Breached)",
            'pending' => "Awaiting first response — {$timing} elapsed (Within SLA)",
            default => 'Not Responded',
        };
    }

    public function getInternalVerificationAttribute(): string { return $this->internalProcessing?->forwarded_at ? ucfirst($this->internalProcessing->verification_status) : 'Direct delivery'; }
    public function getInternalHandoverAttribute(): string { return $this->internalProcessing?->forwarded_at?->format('M d, Y H:i') ?? '—'; }
    public function getClientResponseStartAttribute() { return $this->delivered_at ? ($this->internalProcessing?->forwarded_at ?? $this->created_at) : $this->created_at; }
    public static function clientSlaStartExpression(): string { return '(CASE WHEN leads.internal_processing_pending = 1 THEN NULL ELSE COALESCE((SELECT forwarded_at FROM internal_lead_processing WHERE internal_lead_processing.lead_id = leads.id), leads.created_at) END)'; }

    public function getOrganizationAttribute(): ?Organization
    {
        return $this->client?->organization ?: $this->assignedTo?->organization;
    }

    public function getEffectiveDisplayConfigAttribute(): array
    {
        return $this->organization?->getLeadDisplayConfig() ?? Organization::defaultLeadDisplayConfig();
    }

    public function getOrganizationNameAttribute(): string
    {
        return $this->client?->organization?->name ?: ($this->assignedTo?->organization?->name ?: '-');
    }

    public function getTeamNameAttribute(): string
    {
        $tm = $this->assignedTo?->salesTeamMembers?->first();
        return $tm?->salesTeam?->name ?: '-';
    }

    public function getLastActivityAttribute(): string
    {
        if ($this->first_response_at) {
            return 'Response ' . $this->first_response_at->diffForHumans();
        }
        return 'Created ' . ($this->created_at ? $this->created_at->diffForHumans() : '-');
    }

    public function nextPendingFollowup(): ?Followup
    {
        return $this->followups()
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', Followup::STATUS_PENDING);
            })
            ->orderBy('due_at', 'asc')
            ->first();
    }

    public function lastCompletedFollowup(): ?Followup
    {
        return $this->followups()
            ->where('status', Followup::STATUS_COMPLETED)
            ->orderBy('completed_at', 'desc')
            ->orderBy('due_at', 'desc')
            ->first();
    }

    public function getLastFollowupAttribute(): string
    {
        $f = $this->lastCompletedFollowup();
        if ($f && ($f->completed_at || $f->due_at)) {
            $date = $f->completed_at ?? $f->due_at;
            return Carbon::parse($date)->format('M d, H:i');
        }

        return '-';
    }

    public function getLastFollowupOutcomeAttribute(): ?string
    {
        $f = $this->lastCompletedFollowup();
        if (! $f) {
            return null;
        }

        return $f->outcome_label ?? $f->outcome ?? ($f->outcome_notes ? \Illuminate\Support\Str::limit($f->outcome_notes, 30) : null);
    }

    public function getFollowupUrgencyAttribute(): string
    {
        $f = $this->nextPendingFollowup();
        if (! $f || ! $f->due_at) {
            return 'none';
        }

        if ($f->due_at->isPast()) {
            return 'overdue';
        }
        if ($f->due_at->isToday()) {
            return 'due_today';
        }

        return 'upcoming';
    }

    public function getNextFollowupAttribute(): string
    {
        $f = (static::usesSalesPanel() ? $this->followups->where('status', 'pending') : $this->followups->where('due_at', '>=', now()))->sortBy('due_at')->first();
        if ($f && $f->due_at) {
            return Carbon::parse($f->due_at)->timezone('Asia/Kolkata')->format('d M Y, h:i A');
        }

        return '-';
    }
}
