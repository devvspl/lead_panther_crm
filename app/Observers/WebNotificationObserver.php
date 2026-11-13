<?php

namespace App\Observers;

use App\Models\{Lead, LeadAssignment, LeadReplacement, Meeting, SiteVisit, Project, Campaign, User};
use App\Services\NotificationDeliveryService;
use Illuminate\Database\Eloquent\Model;

class WebNotificationObserver
{
    public function created(Model $model): void { $this->record($model, true); }
    public function updated(Model $model): void { $this->record($model, false); }

    private function record(Model $model, bool $created): void
    {
        $service = app(NotificationDeliveryService::class);
        if ($model instanceof Lead) {
            if ($created && $model->delivery_pending && $model->delivery_at?->isFuture()) {
                $service->leadAlert($model, 'delivery_queued', 'Leads waiting for delivery', "Lead {$model->lead_code} is waiting for its scheduled delivery slot.", 'queue:'.$model->campaign_id.':'.now()->toDateString(), 'delivery', true);
            }
            if (! $created && $model->wasChanged('delivered_at') && $model->delivered_at) {
                $service->leadAlert($model, 'lead_delivered', 'New lead delivered', "Lead {$model->lead_code} has been delivered to your organization.", 'delivered:'.$model->id);
                if ($model->getOriginal('delivery_failed_at')) {
                    $service->leadAlert($model, 'campaign_resumed', 'Campaign delivery resumed', 'Delivery resumed after an earlier failed attempt.',
                        'delivery-resumed:'.$model->campaign_id.':'.now()->toDateString(), 'delivery', true);
                }
            }
            if (! $created && $model->wasChanged('assigned_to')) {
                // The distribution service records assignments before updating the lead.
                // Direct admin edits do not, so cover those without repeating the service alert.
                if (! $model->webAssignmentRecorded) {
                    $key = 'direct-assignment:'.$model->id.':'.\Illuminate\Support\Str::uuid();
                    $service->leadAlert($model, 'lead_assigned', 'Lead reassigned', "Assignment updated for lead {$model->lead_code}.", $key, 'assignment');
                    $old = User::find($model->getOriginal('assigned_to'));
                    if ($old) $service->sendWeb($old, 'lead_reassigned', 'Lead reassigned', "Lead {$model->lead_code} has been reassigned.", $key.':old', 'assignment', User::find($model->client_id)?->organization_id);
                }
            }
            if (! $created && $model->wasChanged('delivery_failed_at') && $model->delivery_failed_at) {
                $service->leadAlert($model, 'delivery_failed', 'Lead delivery failed', "Delivery of {$model->lead_code} failed. Check the delivery queue and available credits.", 'failed:'.$model->id.':'.now()->toDateString(), 'delivery', true);
            }
            if (! $created && $model->wasChanged(['current_stage', 'status'])) {
                $stage = Lead::normalizeStage($model->wasChanged('current_stage') ? $model->current_stage : $model->status);
                if (! in_array($stage, ['new', 'assigned', 'pending_credit']) && ! $model->delivery_pending) {
                    $title = match ($stage) { 'closed_won' => 'Lead marked Closed Won', 'lost' => 'Lead marked Lost', default => 'Lead status changed' };
                    $service->leadAlert($model, 'lead_'.$stage, $title, "Lead {$model->lead_code} is now ".str_replace('_', ' ', $stage).'.',
                        'stage:'.$model->id.':'.$stage.':'.\Illuminate\Support\Str::uuid(), 'assignment');
                }
            }
            return;
        }
        if ($model instanceof Project || $model instanceof Campaign) {
            if (! $created && $model->wasChanged(['delivery_daily_limit', 'delivery_interval_minutes', 'delivery_enabled', 'status'])) {
                $project = $model instanceof Project;
                // Only dirty business fields reach this branch; unchanged job retries do not.
                $key = 'config:'.get_class($model).':'.$model->id.':'.\Illuminate\Support\Str::uuid();
                $service->configurationAlert($model, 'configuration_updated', $project ? 'Project delivery configuration changed' : 'Campaign configuration updated', "Delivery settings for {$model->name} were updated.", $key);
                if ($model->wasChanged(['delivery_enabled', 'status'])) {
                    $active = $model->status === 'active' && (! $project || $model->delivery_enabled);
                    $service->configurationAlert($model, $active ? 'campaign_resumed' : 'campaign_paused',
                        $active ? 'Campaign delivery resumed' : 'Campaign delivery paused', "Delivery for {$model->name} is ".($active ? 'active.' : 'paused.'), $key.':status');
                }
            }
            return;
        }
        $leadId = $model instanceof LeadAssignment || $model instanceof LeadReplacement || $model instanceof Meeting || $model instanceof SiteVisit ? $model->lead_id : null;
        $lead = $leadId ? Lead::withoutGlobalScopes()->find($leadId) : null;
        if (! $lead) return;
        if ($model instanceof LeadAssignment && $created) {
            $new = User::find($model->assigned_to);
            $oldId = $lead->assigned_to;
            $lead->assigned_to = $model->assigned_to;
            $service->leadAlert($lead, 'lead_assigned', 'Lead assigned', "Lead {$lead->lead_code} was assigned to {$new?->name}.", 'assignment:'.$model->id, 'assignment');
            if ($oldId && $oldId != $model->assigned_to && ($old = User::find($oldId))) {
                $service->sendWeb($old, 'lead_reassigned', 'Lead reassigned', "Lead {$lead->lead_code} has been reassigned to another team member.", 'unassigned:'.$model->id,
                    'assignment', User::find($lead->client_id)?->organization_id);
            }
        }
        if ($model instanceof LeadReplacement) {
            if ($created || $model->wasChanged('status')) {
                $status = $created ? 'submitted' : $model->status;
                $service->leadAlert($lead, 'replacement_'.$status, 'Replacement request '.$status,
                    "Replacement request #{$model->id} for {$lead->lead_code} was {$status}.", 'replacement:'.$model->id.':'.$status, 'replacement', true);
                if ($created) $service->leadAlert($lead, 'lead_returned', 'Lead returned for replacement', "Lead {$lead->lead_code} was returned for replacement review.", 'returned:'.$model->id, 'replacement', true, true);
            }
            if ($model->wasChanged('replacement_lead_id') && $model->replacement_lead_id) {
                $service->leadAlert($lead, 'replacement_generated', 'Replacement lead generated', "Replacement lead for {$lead->lead_code} has been generated.", 'replacement-generated:'.$model->id, 'replacement');
            }
        }
        if ($created && ($model instanceof Meeting || $model instanceof SiteVisit)) {
            $label = $model instanceof Meeting ? 'Meeting' : 'Site visit';
            $service->leadAlert($lead, 'activity_scheduled', $label.' scheduled', "{$label} scheduled for lead {$lead->lead_code}.", 'activity:'.get_class($model).':'.$model->id, 'followup');
        }
    }
}
