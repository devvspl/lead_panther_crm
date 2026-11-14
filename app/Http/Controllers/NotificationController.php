<?php
namespace App\Http\Controllers;

use App\Models\{Lead, Project, Campaign, User};
use App\Services\NotificationDeliveryService;

class NotificationController extends Controller
{
    public function open(string $notification)
    {
        $user = auth()->user();
        $record = app(NotificationDeliveryService::class)->managedNotifications($user)->whereKey($notification)->firstOrFail();
        $data = $record->data;
        $entity = $data['entity_type'] ?? (isset($data['lead_id']) ? 'lead' : null);
        $id = $data['entity_id'] ?? $data['lead_id'] ?? null;
        $target = \Illuminate\Support\Facades\Route::has('notifications.index') ? route('notifications.index') : route('dashboard');
        if ($entity === 'lead') {
            $lead = (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->findOrFail($id);
            $target = route('leads.index', ['search' => $lead->lead_code, 'notification_lead' => $lead->id]);
        } elseif (in_array($entity, ['campaign', 'project'])) {
            $campaign = $entity === 'campaign' ? Campaign::withoutGlobalScopes()->findOrFail($id) : null;
            $project = Project::withoutGlobalScopes()->findOrFail($campaign?->project_id ?? $id);
            $owner = User::findOrFail($project->client_id);
            $admin = $user->hasAnyRole(['Super Admin', 'super-admin']);
            abort_unless($admin || (\App\Support\NavigationConfig::getRoleKey($user) === 'builder' && ($user->id === $owner->id || ($owner->organization_id && $owner->organization_id == $user->organization_id))), 403);
            $target = $admin && $owner->organization_id
                ? route('admin.organizations.show', ['organization' => $owner->organization_id, 'tab' => 'campaigns']).'#delivery-'.$entity.'-'.$id
                : route('leads.index', $campaign ? ['campaign' => $campaign->id] : ['project' => $project->id]);
        } elseif ($entity === 'report') {
            $target = route('reports.index');
        }
        $record->markAsRead();
        return redirect()->to($target);
    }
}
