<?php
namespace App\Notifications\Channels;

use App\Models\{User, LeadAssignment};
use App\Services\NotificationDeliveryService;

class WebInboxChannel
{
    public function send($notifiable, $notification): void
    {
        $lead = $notification->lead ?? $notification->followup?->lead;
        if (! $notifiable instanceof User || ! $lead) return;
        $orgId = User::find($lead->client_id)?->organization_id;
        $service = app(NotificationDeliveryService::class);
        if (! $service->organizationRecipients($orgId, $lead->client_id, $lead->assigned_to)->contains('id', $notifiable->id)) return;
        if (isset($notification->followup)) {
            $followup = $notification->followup;
            $service->sendWeb($notifiable, 'followup_due', 'Follow-up reminder due', "Follow-up for {$lead->lead_code} is due.",
                'followup:'.$followup->id.':'.$followup->due_at->toDateTimeString(), 'followup', $orgId, 'lead', $lead->id);
        } else {
            $assignment = LeadAssignment::where('lead_id', $lead->id)->where('assigned_to', $notifiable->id)->latest('id')->first();
            $key = $assignment ? 'assignment:'.$assignment->id : 'assignment-direct:'.$lead->id.':'.$notifiable->id;
            $service->sendWeb($notifiable, 'lead_assigned', 'Lead assigned', "Lead {$lead->lead_code} was assigned to you.", $key, 'assignment', $orgId, 'lead', $lead->id);
        }
    }
}
