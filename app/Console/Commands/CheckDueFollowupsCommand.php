<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Followup;
use App\Models\User;
use App\Notifications\FollowupDueNotification;

class CheckDueFollowupsCommand extends Command
{
    protected $signature = 'followups:check-due';
    protected $description = 'Check and send notifications for due follow-ups';

    public function handle(): int
    {
        $dueFollowups = Followup::where('due_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'pending');
            })
            ->get();

        $count = 0;
        foreach ($dueFollowups as $followup) {
            $lead = \App\Models\Lead::withoutGlobalScopes()->find($followup->lead_id);
            if (! $lead || $lead->delivery_pending) continue;
            $service = app(\App\Services\NotificationDeliveryService::class);
            $key = 'followup:'.$followup->id.':'.$followup->due_at->toDateTimeString();
            $user = User::find($lead->assigned_to);
            $alreadySent = $user && \Illuminate\Notifications\DatabaseNotification::where('event_key', hash('sha256', $user->id.'|'.$key))->exists();
            $service->leadAlert($lead, 'followup_due', 'Follow-up reminder due', "Follow-up for {$lead->lead_code} is due.", $key, 'followup');
            if ($user && ! $alreadySent && $service->webAllowed($user, 'followup', false) && $user->organization_id == User::find($lead->client_id)?->organization_id) {
                $user->notify(new FollowupDueNotification($followup));
            }
            $count++;
        }

        $this->info("Follow-up check completed: notified for {$count} due follow-ups.");
        return Command::SUCCESS;
    }
}
