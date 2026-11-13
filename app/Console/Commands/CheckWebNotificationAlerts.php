<?php
namespace App\Console\Commands;

use App\Models\{Lead, Organization, User};
use App\Services\NotificationDeliveryService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckWebNotificationAlerts extends Command
{
    protected $signature = 'notifications:check-operational';
    protected $description = 'Publish deduplicated SLA alerts and organization daily delivery summaries';

    public function handle(): int
    {
        $service = app(NotificationDeliveryService::class);
        Lead::withoutGlobalScopes()->where('delivery_pending', false)->whereNull('first_response_at')
            ->whereNotNull('delivered_at')->where('delivered_at', '<=', now()->subMinutes(15))
            ->whereNotIn('current_stage', ['closed_won', 'lost', 'invalid', 'replaced', 'rejected'])->chunkById(100, function ($leads) use ($service) {
                foreach ($leads as $lead) $service->leadAlert($lead, 'sla_breached', 'SLA response overdue',
                    "Lead {$lead->lead_code} has not received its first response within 15 minutes of delivery.", 'sla:'.$lead->id, 'sla', true);
            });
        Organization::where('status', 'active')->chunkById(100, function ($organizations) use ($service) {
            foreach ($organizations as $org) {
                $timezone = $org->delivery_timezone ?: config('app.timezone');
                $end = now()->setTimezone($timezone)->startOfDay();
                $start = $end->copy()->subDay();
                $owners = User::where('organization_id', $org->id)->select('id');
                $query = Lead::withoutGlobalScopes()->whereIn('client_id', $owners);
                $delivered = (clone $query)->where('delivered_at', '>=', $start->copy()->setTimezone(config('app.timezone')))
                    ->where('delivered_at', '<', $end->copy()->setTimezone(config('app.timezone')))
                    ->whereNotIn('status', ['duplicate', 'rejected', 'invalid'])->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid'])->count();
                $queued = (clone $query)->where('delivery_pending', true)->whereNotIn('status', ['duplicate', 'rejected', 'invalid'])->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid']);
                $pending = (clone $queued)->count();
                $failed = (clone $queued)->whereNotNull('delivery_failed_at')->count();
                foreach ($service->organizationRecipients($org->id) as $recipient) {
                    $service->sendWeb($recipient, 'daily_summary', 'Daily delivery summary available',
                        "{$org->name}: {$delivered} delivered on {$start->toDateString()}, {$pending} currently queued, {$failed} awaiting retry.",
                        'summary:'.$org->id.':'.$start->toDateString(), 'delivery', $org->id, 'report', $org->id, true);
                }
            }
        });
        return self::SUCCESS;
    }
}
