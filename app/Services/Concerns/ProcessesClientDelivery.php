<?php

namespace App\Services\Concerns;

use App\Models\{AuditLog, Campaign, CreditTransaction, Lead, LeadDeliveryPlan, LeadDeliveryRun, LeadDeliveryItem, Organization, Project, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ProcessesClientDelivery
{
    public const DELIVERY_FREQUENCIES = ['immediate', 'custom_minutes', '15_minutes', '30_minutes', 'random_1_30', 'hourly', 'daily', 'weekly', 'manual'];

    protected function deliveryAdmin(User $actor): void
    {
        abort_unless($actor->status === 'active' && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    public function clientDeliveryRecipients()
    {
        $roles = ['Builder/Developer', 'Builder', 'builder', 'builder-admin', 'Client', 'client', 'Broker', 'broker', 'Channel Partner', 'channel-partner'];
        return User::where('status', 'active')->whereHas('organization', fn($q) => $q->where('status', 'active'))
            ->where(fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', $roles))
                ->orWhereIn('id', \App\Models\Client::whereHas('roles', fn($r) => $r->whereIn('name', $roles))->select('id')));
    }

    protected function deliveryAudit(string $action, $record, array $before, array $after, ?int $actorId): void
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'subject_type' => get_class($record),
            'subject_id' => $record->id,
            'organization_id' => $record instanceof LeadDeliveryPlan ? $record->organization_id : LeadDeliveryPlan::find($record->plan_id)?->organization_id,
            'context' => ['category' => 'lead_delivery', 'status' => str_ends_with($action, '.failed') ? 'failure' : 'success'],
            'from_value' => json_encode($before),
            'to_value' => json_encode($after),
            'created_at' => now()
        ]);
    }

    public function saveClientDeliveryPlan(Organization $source, array $input, User $actor): LeadDeliveryPlan
    {
        $this->deliveryAdmin($actor);
        $input['campaign_id'] = $input['campaign_id'] ?? null;
        if ($input['campaign_id'] === '') $input['campaign_id'] = null;
        // Inactive caps are ignored, including their stale form values.
        if (($input['quantity_mode'] ?? null) === 'all') $input['quantity'] = $input['max_cycle'] = null;
        if (array_key_exists('daily_enabled', $input) && !filter_var($input['daily_enabled'], FILTER_VALIDATE_BOOLEAN)) $input['daily_limit'] = null;
        $data = Validator::make($input, [
            'client_id' => 'required|integer|exists:users,id',
            'project_id' => 'required|integer|exists:projects,id',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'frequency' => ['required', Rule::in(self::DELIVERY_FREQUENCIES)],
            'interval_minutes' => 'required_if:frequency,custom_minutes|nullable|integer|min:1|max:1440',
            'quantity_mode' => ['required', Rule::in(['fixed', 'all'])],
            'quantity' => 'required_if:quantity_mode,fixed|nullable|integer|min:1|max:1000000',
            'max_cycle' => 'nullable|integer|min:1|max:1000000',
            'daily_enabled' => 'required|boolean',
            'daily_limit' => 'required_if:daily_enabled,true|nullable|integer|min:1|max:1000000',
            'automatic' => 'required|boolean',
            'timezone' => 'required|timezone',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d' . (!empty($input['start_date']) ? '|after_or_equal:start_date' : ''),
            'window_start' => 'required|date_format:H:i',
            'window_end' => 'required|date_format:H:i|after:window_start',
            'daily_time' => 'required|date_format:H:i',
            'weekly_day' => 'required|integer|min:0|max:6',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'integer|min:0|max:6|distinct',
            'confirmed_send_all' => 'accepted_if:quantity_mode,all',
            'confirmed_unlimited' => 'accepted_if:daily_enabled,false',
        ], [
            'interval_minutes.required_if' => 'Enter the delivery interval in minutes.',
            'quantity.required_if' => 'Enter how many leads to send.',
            'daily_limit.required_if' => 'Enter a daily limit, or turn the daily limit off.',
        ], [
            'client_id' => 'client',
            'project_id' => 'project',
            'campaign_id' => 'campaign',
            'interval_minutes' => 'delivery interval in minutes',
            'quantity' => 'lead quantity',
            'daily_limit' => 'daily limit',
            'window_start' => 'start time',
            'window_end' => 'end time',
            'daily_time' => 'scheduled time',
            'working_days' => 'working days',
        ])->validate();
        $owner = User::with('organization')->findOrFail($data['client_id']);
        if (!$this->clientDeliveryRecipients()->whereKey($owner->id)->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Choose an active billing client in an active organization.']);
        }
        $project = Project::withoutGlobalScopes()->where('client_id', $owner->id)->find($data['project_id']);
        if (!$project) throw ValidationException::withMessages(['project_id' => 'Choose a project belonging to this client.']);
        if ($data['campaign_id'] !== null && !Campaign::withoutGlobalScopes()->where('project_id', $project->id)->where('status', 'active')->whereKey($data['campaign_id'])->exists()) throw ValidationException::withMessages(['campaign_id' => 'Choose a campaign for this project.']);
        if (in_array($data['frequency'], ['daily', 'weekly']) && ($data['daily_time'] < $data['window_start'] || $data['daily_time'] > $data['window_end'])) throw ValidationException::withMessages(['daily_time' => 'The scheduled time must be inside the delivery window.']);
        if ($data['frequency'] === 'weekly' && !in_array((int) $data['weekly_day'], array_map('intval', $data['working_days']))) throw ValidationException::withMessages(['weekly_day' => 'The weekly day must also be a working day.']);
        unset($data['client_id'], $data['project_id'], $data['campaign_id'], $data['confirmed_send_all'], $data['confirmed_unlimited']);
        return DB::transaction(function () use ($source, $input, $data, $actor) {
            Organization::whereKey($source->id)->lockForUpdate()->firstOrFail();
            $plan = LeadDeliveryPlan::firstOrNew(['organization_id' => $source->id]);
            $before = $plan->exists ? $plan->getAttributes() : [];
            if ($data['frequency'] === 'random_1_30') {
                $randomMinutes = random_int(1, 30);
                $data['random_interval_minutes'] = $randomMinutes;
                $plan->fill(['client_id' => $input['client_id'], 'project_id' => $input['project_id'], 'campaign_id' => $input['campaign_id'], 'settings' => $data, 'created_by' => $plan->created_by ?: $actor->id]);
                if (!$plan->exists) $plan->state = 'active';
                $after = now()->addMinutes($randomMinutes);
                $plan->next_run_at = $data['automatic'] && $plan->state === 'active' ? $this->nextClientDeliveryAt($plan, $after) : null;
            } elseif ($data['frequency'] === 'custom_minutes') {
                $interval = max(1, (int) ($data['interval_minutes'] ?? 20));
                $data['interval_minutes'] = $interval;
                $plan->fill(['client_id' => $input['client_id'], 'project_id' => $input['project_id'], 'campaign_id' => $input['campaign_id'], 'settings' => $data, 'created_by' => $plan->created_by ?: $actor->id]);
                if (!$plan->exists) $plan->state = 'active';
                $after = now()->addMinutes($interval);
                $plan->next_run_at = $data['automatic'] && $plan->state === 'active' ? $this->nextClientDeliveryAt($plan, $after) : null;
            } else {
                $plan->fill(['client_id' => $input['client_id'], 'project_id' => $input['project_id'], 'campaign_id' => $input['campaign_id'], 'settings' => $data, 'created_by' => $plan->created_by ?: $actor->id]);
                if (!$plan->exists) $plan->state = 'active';
                $plan->next_run_at = $data['automatic'] && $plan->state === 'active' ? $this->nextClientDeliveryAt($plan, now()) : null;
            }
            $plan->save();
            $this->deliveryAudit('delivery.plan_configured', $plan, $before, $plan->getAttributes(), $actor->id);
            return $plan;
        }, 3);
    }

    /** Returns the next permitted calendar slot; application timestamps remain in the application timezone. */
    public function nextClientDeliveryAt(LeadDeliveryPlan $plan, Carbon $after): ?Carbon
    {
        $s = $plan->settings;
        if ($s['frequency'] === 'manual') return null;
        $local = $after->copy()->setTimezone($s['timezone']);
        if (!empty($s['start_date']) && $local->toDateString() < $s['start_date']) $local = Carbon::parse($s['start_date'], $s['timezone'])->startOfDay();
        for ($day = 0; $day <= 370; $day++) {
            $date = $local->copy()->startOfDay()->addDays($day);
            if (!empty($s['end_date']) && $date->toDateString() > $s['end_date']) return null;
            if (!in_array($date->dayOfWeek, array_map('intval', $s['working_days']))) continue;
            if ($s['frequency'] === 'weekly' && $date->dayOfWeek !== (int) $s['weekly_day']) continue;
            $start = $date->copy()->setTimeFromTimeString($s['window_start']);
            $end = $date->copy()->setTimeFromTimeString($s['window_end']);
            $at = in_array($s['frequency'], ['daily', 'weekly']) ? $date->copy()->setTimeFromTimeString($s['daily_time']) : $start->max($local);
            if ($at->lt($local) || $at->lt($start) || $at->gt($end)) continue;
            return $at->setTimezone(config('app.timezone'));
        }
        return null;
    }

    protected function insideClientDeliveryWindow(LeadDeliveryPlan $plan): bool
    {
        $s = $plan->settings;
        $now = now()->setTimezone($s['timezone']);
        return (empty($s['start_date']) || $now->toDateString() >= $s['start_date']) && (empty($s['end_date']) || $now->toDateString() <= $s['end_date'])
            && in_array($now->dayOfWeek, array_map('intval', $s['working_days'])) && $now->format('H:i') >= $s['window_start'] && $now->format('H:i') <= $s['window_end'];
    }

    public function eligibleClientDeliveryLeads(int $sourceOrganizationId)
    {
        return $this->applyClientDeliveryApproval($this->clientDeliveryCandidates($sourceOrganizationId), $sourceOrganizationId);
    }

    protected function clientDeliveryCandidates(int $sourceOrganizationId)
    {
        return Lead::withoutGlobalScopes()->whereIn('client_id', User::where('organization_id', $sourceOrganizationId)->select('id'))
            ->whereNull('delivered_at')
            ->where(fn($q) => $q->whereNull('delivery_plan_id')->orWhereIn('delivery_plan_id', LeadDeliveryPlan::where('organization_id', $sourceOrganizationId)->select('id')))
            ->whereNotIn('id', CreditTransaction::withoutGlobalScopes()->where('is_legacy', false)->whereNotNull('lead_id')->select('lead_id'))
            ->whereNotIn('status', ['duplicate', 'rejected', 'invalid', 'lost', 'closed_lost', 'closed_won', 'replaced'])
            ->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid', 'lost', 'closed_lost', 'closed_won', 'replaced']);
    }

    /** The saved organization switch controls whether internal approval is required. */
    public function applyClientDeliveryApproval($query, int $organizationId)
    {
        $organization = Organization::find($organizationId);
        if (!$organization) return $query->whereRaw('1 = 0');
        if (!$organization->internal_processing_enabled) return $query;
        return $query->whereHas('internalProcessing', fn ($q) => $q->where('organization_id', $organizationId)
            ->where('verification_status', 'verified')->whereNotNull('verified_at')
            ->whereNotNull('handover_requested_at')->whereNotNull('handover_requested_by')
            ->whereIn('status_code', $organization->internal_processing_config['forward_statuses'] ?? []));
    }

    protected function clientDeliveryCampaign(LeadDeliveryPlan $plan, Lead $lead): int
    {
        if ($plan->campaign_id) return $plan->campaign_id;
        Project::withoutGlobalScopes()->whereKey($plan->project_id)->lockForUpdate()->firstOrFail();
        if (Campaign::withoutGlobalScopes()->whereKey($lead->campaign_id)->where('project_id', $plan->project_id)->where('status', 'active')->exists()) return $lead->campaign_id;
        // Reuse the CRM's project-only lead mapping instead of assigning an unrelated campaign.
        $destination = new Lead(['lead_source_id' => $lead->lead_source_id]);
        $destination->project_id = $plan->project_id;
        return $destination->campaign_id;
    }

    /** Discover intake and older records without manufacturing sharing consent. Caller holds the plan lock. */
    protected function enrollAutomaticClientDelivery(LeadDeliveryPlan $plan): void
    {
        if (Organization::find($plan->organization_id)?->internal_processing_enabled) {
            // Intake that predates enabling the switch must enter the existing internal queue too.
            $this->clientDeliveryCandidates($plan->organization_id)->whereDoesntHave('internalProcessing')
                ->orderBy('id')->chunkById(100, function ($leads) use ($plan) {
                    foreach ($leads as $candidate) {
                        $lead = $this->clientDeliveryCandidates($plan->organization_id)->whereKey($candidate->id)->lockForUpdate()->first();
                        if (!$lead || $lead->internalProcessing()->exists()) continue;
                        $lead->update(['internal_processing_pending' => true, 'delivery_pending' => true, 'delivery_plan_id' => $plan->id, 'delivery_at' => null]);
                        $this->startInternalProcessing($lead);
                        $this->captureClientDeliveryLead($lead);
                    }
                });
        }
        $this->eligibleClientDeliveryLeads($plan->organization_id)
            ->whereNotIn('id', LeadDeliveryItem::select('lead_id'))
            ->orderBy('id')->chunkById(100, function ($leads) use ($plan) {
                foreach ($leads as $candidate) {
                    $lead = $this->eligibleClientDeliveryLeads($plan->organization_id)->whereKey($candidate->id)->lockForUpdate()->first();
                    if (!$lead || LeadDeliveryItem::where('lead_id', $lead->id)->exists()) continue;
                    $item = LeadDeliveryItem::create([
                        'plan_id' => $plan->id,
                        'lead_id' => $lead->id,
                        'source_client_id' => $lead->client_id,
                        'source_campaign_id' => $lead->campaign_id,
                        'client_id' => $plan->client_id,
                        'project_id' => $plan->project_id,
                        'campaign_id' => $this->clientDeliveryCampaign($plan, $lead),
                        'requested_by' => $plan->created_by,
                        'status' => 'pending',
                    ]);
                    $lead->update(['delivery_plan_id' => $plan->id, 'delivery_pending' => true]);
                    $this->deliveryAudit('delivery.auto_enrolled', $item, [], $item->getAttributes(), $plan->created_by);
                }
            });
    }

    public function captureClientDeliveryLead(Lead $lead): void
    {
        $plan = LeadDeliveryPlan::findOrFail($lead->delivery_plan_id);
        $item = LeadDeliveryItem::firstOrCreate(['lead_id' => $lead->id], [
            'plan_id' => $plan->id,
            'source_client_id' => $lead->client_id,
            'source_campaign_id' => $lead->campaign_id,
            'client_id' => $plan->client_id,
            'project_id' => $plan->project_id,
            'campaign_id' => $this->clientDeliveryCampaign($plan, $lead),
            'requested_by' => $plan->created_by,
            'status' => 'pending',
        ]);
        if ($item->wasRecentlyCreated) $this->deliveryAudit('delivery.intake_captured', $item, [], $item->getAttributes(), $plan->created_by);
        if ($lead->delivery_consent_at && $this->applyClientDeliveryApproval(Lead::withoutGlobalScopes()->whereKey($lead->id), $plan->organization_id)->exists() && $plan->state === 'active' && $plan->settings['automatic'] && $plan->settings['frequency'] === 'immediate') {
            DB::afterCommit(function () use ($plan) {
                if ($run = $this->startClientDeliveryCycle($plan->id)) \App\Jobs\ProcessClientDeliveryRun::dispatch($run->id);
            });
        }
    }

    public function enqueueClientLeads(LeadDeliveryPlan $plan, array $ids, bool $consentConfirmed, User $actor): array
    {
        $this->deliveryAdmin($actor);
        Validator::make(['ids' => $ids, 'consent' => $consentConfirmed], ['ids' => 'required|array|min:1', 'ids.*' => 'required|integer|distinct', 'consent' => 'accepted'])->validate();
        return DB::transaction(function () use ($plan, $ids, $actor) {
            $plan = LeadDeliveryPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $sourceIds = User::where('organization_id', $plan->organization_id)->select('id');
            $owned = Lead::withoutGlobalScopes()->whereIn('id', $ids)->whereIn('client_id', $sourceIds)->count();
            abort_unless($owned === count($ids), 403);
            $eligibleIds = $this->eligibleClientDeliveryLeads($plan->organization_id)->whereIn('id', $ids)->pluck('id');
            $count = 0;
            foreach (Lead::withoutGlobalScopes()->whereIn('id', $eligibleIds)->orderBy('id')->lockForUpdate()->get() as $lead) {
                if ($item = LeadDeliveryItem::where('lead_id', $lead->id)->first()) {
                    if ($item->plan_id === $plan->id && $item->status === 'pending' && !$lead->delivery_consent_at) {
                        $lead->update(['delivery_consent_at' => now(), 'delivery_consent_by' => $actor->id]);
                        $this->deliveryAudit('delivery.consent_confirmed', $item, [], ['lead_id' => $lead->id, 'confirmed_by' => $actor->id], $actor->id);
                        $count++;
                    }
                    continue;
                }
                if (!$this->eligibleClientDeliveryLeads($plan->organization_id)->whereKey($lead->id)->exists() || LeadDeliveryItem::where('lead_id', $lead->id)->exists() || CreditTransaction::withoutGlobalScopes()->where('lead_id', $lead->id)->exists()) continue;
                $item = LeadDeliveryItem::create([
                    'plan_id' => $plan->id,
                    'lead_id' => $lead->id,
                    'source_client_id' => $lead->client_id,
                    'source_campaign_id' => $lead->campaign_id,
                    'client_id' => $plan->client_id,
                    'project_id' => $plan->project_id,
                    'campaign_id' => $this->clientDeliveryCampaign($plan, $lead),
                    'requested_by' => $actor->id,
                    'status' => 'pending'
                ]);
                $lead->update(['delivery_plan_id' => $plan->id, 'delivery_pending' => true, 'delivery_consent_at' => now(), 'delivery_consent_by' => $actor->id]);
                $this->deliveryAudit('delivery.enqueued', $item, [], array_merge($item->getAttributes(), ['consent_confirmed_by' => $actor->id, 'consent_confirmed_at' => now()->toIso8601String()]), $actor->id);
                $count++;
            }
            return ['queued' => $count, 'skipped' => count($ids) - $count];
        }, 3);
    }

    public function remainingClientDeliveryCapacity(LeadDeliveryPlan $plan): ?int
    {
        if (!$plan->settings['daily_enabled']) return null;
        [$start, $end] = $this->deliveryDay(now(), $plan->settings['timezone']);
        $sent = $plan->items()->where('sent_at', '>=', $start)->where('sent_at', '<', $end)->count();
        return max(0, (int) $plan->settings['daily_limit'] - $sent);
    }

    public function clientDeliveryHealth(LeadDeliveryPlan $plan): array
    {
        $alerts = [];
        $owner = User::with('organization')->find($plan->client_id);
        $project = Project::withoutGlobalScopes()->find($plan->project_id);
        if (!$this->clientDeliveryRecipients()->whereKey($plan->client_id)->exists()) $alerts[] = ['type' => 'error', 'message' => 'Destination client is inactive, unauthorized or belongs to an inactive organization.'];
        if (!$project || $project->client_id !== $plan->client_id || $project->status !== 'active' || !$project->delivery_enabled) $alerts[] = ['type' => 'error', 'message' => 'Destination project is inactive, delivery is disabled, or its client mapping is invalid.'];
        if ($plan->campaign_id && !Campaign::withoutGlobalScopes()->whereKey($plan->campaign_id)->where('project_id', $plan->project_id)->where('status', 'active')->exists()) $alerts[] = ['type' => 'error', 'message' => 'Selected destination campaign is inactive or mapped to another project.'];
        $balance = (int)(\App\Models\CreditWallet::withoutGlobalScopes()->where('client_id', $plan->client_id)->value('balance') ?? 0);
        $cost = $project ? (int)app(\App\Services\LeadPricingService::class)->resolveCost($project, null, $owner?->organization)['cost'] : null;
        if ($cost !== null && $balance < $cost) $alerts[] = ['type' => 'error', 'message' => 'Insufficient credits: wallet ' . number_format($balance) . ', credits per delivered lead ' . number_format($cost) . '. Add lead credits for the destination client.'];
        if (\App\Models\CreditWallet::where('client_id', $plan->client_id)->whereNull('reconciled_at')->exists()) $alerts[] = ['type' => 'error', 'message' => 'Lead credits need Super Admin reconciliation before delivery.'];
        $items = $plan->items();
        $awaiting = (clone $items)->whereIn('status', ['pending', 'queued'])
            ->whereHas('lead', fn ($q) => $q->whereNotIn('id', $this->applyClientDeliveryApproval(Lead::withoutGlobalScopes(), $plan->organization_id)->select('id')))->count();
        $ready = $this->readyClientDeliveryLeads($plan->organization_id)->count();
        if (!$plan->settings['automatic']) $alerts[] = ['type' => 'warning', 'message' => 'Automatic delivery is disabled.'];
        if ($plan->state !== 'active') $alerts[] = ['type' => 'warning', 'message' => 'Delivery is ' . $plan->state . '. Resume it to send leads.'];
        if ($awaiting) $alerts[] = ['type' => 'warning', 'message' => $awaiting . ' lead(s) await Internal Processing verification and handover approval.'];
        if ($plan->settings['daily_enabled']) $alerts[] = ['type' => 'info', 'message' => 'Daily limit: ' . $plan->settings['daily_limit'] . ' successful leads. Remaining today: ' . $this->remainingClientDeliveryCapacity($plan) . '.'];
        if ($this->remainingClientDeliveryCapacity($plan) === 0) $alerts[] = ['type' => 'warning', 'message' => 'Daily capacity is exhausted. Pending leads wait until the next delivery day.'];
        $lastFailure = (clone $items)->where('status', 'failed')->whereNotNull('failure_reason')->latest('updated_at')->first();
        if ($lastFailure) $alerts[] = ['type' => 'error', 'message' => 'Lead #' . $lastFailure->lead_id . ': ' . $lastFailure->failure_reason];
        $queued = $plan->runs()->where('status', 'queued')->whereNull('started_at')->first();
        if ($queued) $alerts[] = ['type' => 'warning', 'message' => 'Batch #' . $queued->id . ' has not started. Send Cycle Now processes a lead immediately; check Scheduled Tasks → Process Background Queue for worker errors.'];
        if (\Illuminate\Support\Facades\Schema::hasTable('scheduled_task_runs')) {
            $worker = \App\Models\ScheduledTaskRun::where('task_key', 'queue-worker')->latest('id')->first();
            if ($worker?->status === 'failed') $alerts[] = ['type' => 'error', 'message' => 'Queue worker failed: ' . $worker->error];
        }
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            $id = DB::selectOne("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lead_status_history' AND COLUMN_NAME='id'");
            if ($id && !str_contains(strtolower($id->EXTRA), 'auto_increment')) $alerts[] = ['type' => 'error', 'message' => 'Database schema repair required: lead_status_history.id is missing AUTO_INCREMENT. Run pending migrations before delivery.'];
        }
        return ['alerts' => $alerts, 'ready' => $ready, 'awaiting' => $awaiting, 'balance' => $balance, 'cost' => $cost];
    }

    public function readyClientDeliveryLeads(int $organizationId)
    {
        $newLeads = $this->eligibleClientDeliveryLeads($organizationId)
            ->whereNotIn('id', LeadDeliveryItem::select('lead_id'))->select('id');
        $waiting = LeadDeliveryItem::whereIn('plan_id', LeadDeliveryPlan::where('organization_id', $organizationId)->select('id'))
            ->whereIn('status', ['pending', 'queued'])->select('lead_id');
        return $this->applyClientDeliveryApproval(Lead::withoutGlobalScopes(), $organizationId)
            ->where(fn($q) => $q->whereIn('id', $newLeads)->orWhereIn('id', $waiting))
            ->whereNull('delivered_at')
            ->whereNotIn('status', ['duplicate', 'rejected', 'invalid', 'lost', 'closed_lost', 'closed_won', 'replaced'])
            ->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid', 'lost', 'closed_lost', 'closed_won', 'replaced']);
    }

    public function startClientDeliveryCycle(int $planId, bool $manual = false): ?LeadDeliveryRun
    {
        return DB::transaction(function () use ($planId, $manual) {
            $plan = LeadDeliveryPlan::whereKey($planId)->lockForUpdate()->firstOrFail();
            if ($plan->state !== 'active') return null;
            // Enrollment is independent of the delivery window; sending still obeys every saved limit.
            if ($manual || $plan->settings['automatic']) $this->enrollAutomaticClientDelivery($plan);
            if (!$this->insideClientDeliveryWindow($plan)) return null;
            if (!$manual && (!$plan->settings['automatic'] || !$plan->next_run_at || $plan->next_run_at->isFuture())) return null;
            if ($existing = $plan->runs()->whereIn('status', ['queued', 'running', 'paused'])->first()) return $existing;
            // Business failures do not throw queue exceptions. Retry them in a fresh audited batch,
            // with bounded exponential backoff, while retaining every previous attempt.
            if ($plan->settings['automatic']) {
                $plan->items()->where('status', 'failed')->whereNull('sent_at')
                    ->whereHas('lead', fn($q) => $this->applyClientDeliveryApproval($q, $plan->organization_id)->whereNull('delivered_at')
                        ->whereNotIn('status', ['duplicate', 'rejected', 'invalid', 'lost', 'closed_lost', 'closed_won', 'replaced'])
                        ->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid', 'lost', 'closed_lost', 'closed_won', 'replaced']))
                    ->orderBy('id')->chunkById(100, function ($items) use ($plan) {
                        foreach ($items as $item) {
                            $delay = min(3600, 60 * (2 ** min(6, max(0, $item->attempts - 1))));
                            if ($item->updated_at->copy()->addSeconds($delay)->isFuture()) continue;
                            $item->update(['status' => 'pending', 'run_id' => null]);
                            $this->deliveryAudit('delivery.retry_scheduled', $item, ['status' => 'failed'], ['status' => 'pending', 'attempts' => $item->attempts], $plan->created_by);
                        }
                    });
            }
            $s = $plan->settings;
            $capacity = $this->remainingClientDeliveryCapacity($plan);
            $pending = $plan->items()->where('status', 'pending')->whereHas('lead', fn($q) => $this->applyClientDeliveryApproval($q, $plan->organization_id));
            $quantity = $s['quantity_mode'] === 'all' ? (clone $pending)->count() : min((int) $s['quantity'], (int) ($s['max_cycle'] ?: $s['quantity']));
            if ($capacity !== null) $quantity = min($quantity, $capacity);
            if ($quantity < 1) {
                $this->advanceClientDelivery($plan, $capacity === 0);
                return null;
            }
            $ids = (clone $pending)->orderBy('attempts')->orderBy('id')->limit($quantity)->pluck('id');
            if ($ids->isEmpty()) {
                $this->advanceClientDelivery($plan);
                return null;
            }
            $run = $plan->runs()->create(['status' => 'queued', 'settings' => $s, 'total' => $ids->count()]);
            $plan->items()->whereIn('id', $ids)->update(['run_id' => $run->id, 'status' => 'queued', 'failure_reason' => null]);
            $this->deliveryAudit('delivery.cycle_started', $run, [], ['total' => $run->total, 'manual' => $manual], $plan->created_by);
            return $run;
        }, 3);
    }

    protected function advanceClientDelivery(LeadDeliveryPlan $plan, bool $dailyExhausted = false): void
    {
        $s = $plan->settings;
        $minutes = match ($s['frequency']) {
            'custom_minutes' => max(1, (int) ($s['interval_minutes'] ?? 20)),
            '15_minutes' => 15,
            '30_minutes' => 30,
            'random_1_30' => random_int(1, 30),
            'hourly' => 60,
            default => 1
        };
        if ($s['frequency'] === 'random_1_30') {
            $s['random_interval_minutes'] = $minutes;
        }
        $after = now()->addMinutes($minutes);
        if ($dailyExhausted) $after = now()->setTimezone($s['timezone'])->addDay()->startOfDay()->setTimezone(config('app.timezone'));
        $plan->update([
            'settings' => $s,
            'next_run_at' => $s['automatic'] ? $this->nextClientDeliveryAt($plan, $after) : null
        ]);
    }

    /** Called in the existing release transaction, under the plan lock held by processClientDeliveryRun. */
    protected function plannedReleaseAllowed(Lead $lead, ?int $runId): bool
    {
        if (!$lead->delivery_plan_id) return true;
        if (!$runId) return false;
        $run = LeadDeliveryRun::find($runId);
        $plan = LeadDeliveryPlan::find($lead->delivery_plan_id);
        return $run && $plan && $run->plan_id === $plan->id && in_array($run->status, ['queued', 'running']) && $plan->state === 'active'
            && $this->insideClientDeliveryWindow($plan) && $this->remainingClientDeliveryCapacity($plan) !== 0
            && $this->applyClientDeliveryApproval(Lead::withoutGlobalScopes()->whereKey($lead->id), $plan->organization_id)->exists()
            && $plan->items()->where('run_id', $runId)->where('lead_id', $lead->id)->where('status', 'queued')->exists();
    }

    public function processClientDeliveryRun(int $runId, int $chunkSize = 100): bool
    {
        for ($i = 0; $i < $chunkSize; $i++) {
            $progressed = DB::transaction(function () use ($runId) {
                $ref = LeadDeliveryRun::findOrFail($runId);
                $plan = LeadDeliveryPlan::whereKey($ref->plan_id)->lockForUpdate()->firstOrFail();
                $run = LeadDeliveryRun::whereKey($runId)->lockForUpdate()->firstOrFail();
                if ($plan->state !== 'active' || !in_array($run->status, ['queued', 'running'])) return false;
                if (!$this->insideClientDeliveryWindow($plan) || $this->remainingClientDeliveryCapacity($plan) === 0) {
                    $run->items()->where('status', 'queued')->update(['status' => 'pending', 'run_id' => null]);
                    $run->update(['status' => 'completed', 'finished_at' => now()]);
                    $this->advanceClientDelivery($plan, $this->remainingClientDeliveryCapacity($plan) === 0);
                    return false;
                }
                $item = $run->items()->where('status', 'queued')->orderBy('id')->lockForUpdate()->first();
                if (!$item) {
                    $run->update(['status' => 'completed', 'finished_at' => now()]);
                    $this->advanceClientDelivery($plan);
                    return false;
                }
                $run->update(['status' => 'running', 'started_at' => $run->started_at ?: now()]);
                $client = User::with('organization')->whereKey($item->client_id)->lockForUpdate()->first();
                if ($client) Organization::whereKey($client->organization_id)->whereNotNull('assignment_config')->lockForUpdate()->first();
                $project = Project::withoutGlobalScopes()->whereKey($item->project_id)->lockForUpdate()->first();
                $lead = Lead::withoutGlobalScopes()->whereKey($item->lead_id)->lockForUpdate()->first();
                $reason = null;
                if (!$client || !$this->clientDeliveryRecipients()->whereKey($item->client_id)->exists()) $reason = 'Destination client is ineligible or its organization is inactive.';
                elseif (!$project || $project->client_id !== $client->id || !Campaign::withoutGlobalScopes()->whereKey($item->campaign_id)->where('project_id', $project->id)->exists()) $reason = 'Destination project or campaign mapping is invalid.';
                elseif (!$lead || $lead->delivered_at) $reason = 'Lead is unavailable or already delivered.';
                elseif (!$this->applyClientDeliveryApproval(Lead::withoutGlobalScopes()->whereKey($lead->id), $plan->organization_id)->exists()) $reason = 'Lead awaits Internal Processing verification and handover approval.';
                elseif (in_array($lead->status, ['duplicate', 'rejected', 'invalid', 'lost', 'closed_lost', 'closed_won', 'replaced']) || in_array($lead->current_stage, ['duplicate', 'rejected', 'invalid', 'lost', 'closed_lost', 'closed_won', 'replaced'])) $reason = 'Lead is no longer eligible.';
                elseif (Lead::withoutGlobalScopes()->where('client_id', $client->id)->whereNotNull('delivered_at')->where('id', '!=', $lead->id)->where(function ($q) use ($lead) {
                    $digits = preg_replace('/\D/', '', $lead->mobile ?? '');
                    $q->whereIn('mobile', [$lead->mobile, $digits, '+' . $digits, '+91' . substr($digits, -10), substr($digits, -10)]);
                    if ($lead->email) $q->orWhere('email', $lead->email);
                })->exists()) $reason = 'A matching customer lead was already delivered to this client.';
                if (!$reason) {
                    $lead->update([
                        'client_id' => $item->client_id,
                        'campaign_id' => $item->campaign_id,
                        'assigned_to' => null,
                        'delivery_at' => now(),
                        'delivery_config' => ['limit' => 0, 'interval' => 0, 'timezone' => $plan->settings['timezone']],
                        'delivery_failed_at' => null,
                        'delivery_error' => null
                    ]);
                    if (!$this->releaseDelivery($lead->id, $run->id)) $reason = $lead->fresh()->delivery_error ?: 'Delivery held: check active project/campaign, eligibility and available credits.';
                }
                $item->update(['status' => $reason ? 'failed' : 'sent', 'failure_reason' => $reason, 'attempts' => $item->attempts + 1, 'sent_at' => $reason ? null : now()]);
                $item->attemptHistory()->create(['run_id' => $run->id, 'status' => $item->status, 'failure_reason' => $reason]);
                $this->deliveryAudit('delivery.' . $item->status, $item, [], ['lead_id' => $item->lead_id, 'run_id' => $run->id, 'reason' => $reason], $item->requested_by);
                if ($reason && $lead && !$lead->fresh()->delivery_failed_at) {
                    foreach (app(\App\Services\NotificationDeliveryService::class)->organizationRecipients($plan->organization_id, null, null, true) as $admin) {
                        app(\App\Services\NotificationDeliveryService::class)->sendWeb(
                            $admin,
                            'delivery_failed',
                            'Client lead transfer failed',
                            $lead->lead_code . ': ' . $reason,
                            'client-transfer-failed:' . $item->id . ':' . $item->attempts,
                            'delivery',
                            $plan->organization_id,
                            'lead',
                            $lead->id,
                            true
                        );
                    }
                }
                if (!$reason) $this->distribute($lead->fresh());
                return true;
            }, 3);
            if (!$progressed) return false;
        }
        return DB::transaction(function () use ($runId) {
            $ref = LeadDeliveryRun::findOrFail($runId);
            $plan = LeadDeliveryPlan::whereKey($ref->plan_id)->lockForUpdate()->firstOrFail();
            $run = LeadDeliveryRun::whereKey($runId)->lockForUpdate()->firstOrFail();
            if ($plan->state !== 'active' || !in_array($run->status, ['queued', 'running'])) return false;
            if ($run->items()->where('status', 'queued')->exists()) return true;
            $run->update(['status' => 'completed', 'finished_at' => now()]);
            $this->advanceClientDelivery($plan);
            return false;
        }, 3);
    }

    public function controlClientDelivery(LeadDeliveryPlan $plan, string $action, User $actor): void
    {
        $this->deliveryAdmin($actor);
        abort_unless(in_array($action, ['pause', 'resume', 'stop']), 422);
        DB::transaction(function () use ($plan, $action, $actor) {
            $plan = LeadDeliveryPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $before = $plan->state;
            $plan->update(['state' => match ($action) {
                'pause' => 'paused',
                'stop' => 'stopped',
                default => 'active'
            }]);
            foreach ($plan->runs()->whereIn('status', ['queued', 'running', 'paused'])->get() as $run) {
                if ($action === 'stop') $run->items()->where('status', 'queued')->update(['status' => 'pending', 'run_id' => null]);
                $run->update(['status' => match ($action) {
                    'pause' => 'paused',
                    'stop' => 'stopped',
                    default => 'queued'
                }]);
                if ($action === 'resume') \App\Jobs\ProcessClientDeliveryRun::dispatch($run->id)->afterCommit();
            }
            $resumeAfter = now();
            $s = $plan->settings;
            if ($action === 'resume' && ($s['frequency'] ?? '') === 'random_1_30') {
                $randomMinutes = random_int(1, 30);
                $s['random_interval_minutes'] = $randomMinutes;
                $plan->settings = $s;
                $resumeAfter = now()->addMinutes($randomMinutes);
            } elseif ($action === 'resume' && ($s['frequency'] ?? '') === 'custom_minutes') {
                $interval = max(1, (int) ($s['interval_minutes'] ?? 20));
                $resumeAfter = now()->addMinutes($interval);
            }
            $plan->update([
                'settings' => $s,
                'next_run_at' => $action === 'resume' && ($s['automatic'] ?? false) ? $this->nextClientDeliveryAt($plan, $resumeAfter) : null
            ]);
            $this->deliveryAudit('delivery.' . $action, $plan, ['state' => $before], ['state' => $plan->state], $actor->id);
        }, 3);
    }

    public function retryClientDelivery(LeadDeliveryItem $item, User $actor): void
    {
        $this->deliveryAdmin($actor);
        DB::transaction(function () use ($item, $actor) {
            LeadDeliveryPlan::whereKey($item->plan_id)->lockForUpdate()->firstOrFail();
            $item = LeadDeliveryItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->status !== 'failed') throw ValidationException::withMessages(['delivery' => 'Only failed transfers can be retried.']);
            $item->update(['status' => 'pending', 'run_id' => null, 'failure_reason' => null]);
            $this->deliveryAudit('delivery.retry_requested', $item, ['status' => 'failed'], ['status' => 'pending'], $actor->id);
        });
    }
}
