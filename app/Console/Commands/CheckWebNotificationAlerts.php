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
        Lead::withoutGlobalScopes()->where('delivery_pending', false)->where('internal_processing_pending', false)->whereNull('executive_first_response_at')
            ->whereNotNull('assigned_to')->whereNotNull('executive_assigned_at')->where('executive_response_due_at', '<=', now()->addMinutes(5))->whereNotNull('delivered_at')
            ->whereNotIn('current_stage', ['closed_won', 'lost', 'invalid', 'replaced', 'rejected'])->chunkById(100, function ($leads) use ($service) {
                foreach ($leads as $lead) {
                    $due = $lead->executive_response_due_at;
                    $minutes = max(1, (int) $lead->executive_assigned_at->diffInMinutes($due));
                    $warning = $due->copy()->subMinutes(min(5, max(1, (int) ceil($minutes * .2))));
                    if (now()->lt($warning)) continue;
                    $breached = now()->gt($due);
                    $type = $breached ? 'sla_breached' : 'sla_approaching';
                    $assignmentId = \App\Models\LeadAssignment::where('lead_id', $lead->id)->where('assigned_to', $lead->assigned_to)
                        ->whereNull('unassigned_at')->latest('id')->value('id');
                    $service->leadAlert($lead, $type, $breached ? 'Executive response overdue' : 'Executive response deadline approaching',
                        "Lead {$lead->lead_code}: first response due by {$due->format('d M H:i T')} ({$minutes} minutes from assignment).",
                        $type.':'.$lead->id.':'.$lead->assigned_to.':'.($assignmentId ?? $lead->executive_assigned_at->timestamp), 'sla', true);
                }
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
