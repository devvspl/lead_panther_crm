<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\WhatsAppGateway;
use App\Contracts\SmsGateway;
use App\Services\Gateways\LogWhatsAppGateway;
use App\Services\Gateways\LogSmsGateway;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadMessage;
use App\Models\LeadCommunication;
use App\Models\SiteVisit;
use App\Models\CreditTransaction;
use App\Models\LeadReplacement;
use App\Models\User;
use App\Observers\AuditLogObserver;
use App\Observers\FirstResponseObserver;
use App\Observers\LeadScoreObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppGateway::class, LogWhatsAppGateway::class);
        $this->app->bind(SmsGateway::class, LogSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin Gate Bypass
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return ($user->hasRole('Super Admin') || $user->hasRole('super-admin')) ? true : null;
        });

        // Audit Trail Model Observers
        $auditableModels = [
            \App\Models\Lead::class,
            \App\Models\CreditTransaction::class,
            \App\Models\LeadReplacement::class,
            \App\Models\User::class,
            \App\Models\Organization::class,
            \App\Models\Project::class,
            \App\Models\Client::class,
            \App\Models\DistributionRule::class,
            \App\Models\SalesTeam::class,
            \App\Models\SalesTeamMember::class,
            \App\Models\CreditPackage::class,
            \App\Models\CreditWallet::class,
            \App\Models\Followup::class,
            \App\Models\GeneralSetting::class,
            \App\Models\IntegrationCredential::class,
            \App\Models\LeadSource::class,
            \App\Models\Campaign::class,
            \App\Models\Meeting::class,
            \App\Models\Negotiation::class,
            \App\Models\NotificationTemplate::class,
            \App\Models\Payment::class,
            \App\Models\PaymentReceived::class,
            \App\Models\PortalAccount::class,
            \App\Models\Proposal::class,
            \App\Models\RechargeRequest::class,
            \App\Models\SiteVisit::class,
            \App\Models\UploadBatch::class,
            \App\Models\LeadActivity::class,
            \App\Models\LeadAssignment::class,
            \App\Models\LeadCall::class,
            \App\Models\LeadCommunication::class,
            \App\Models\LeadMessage::class,
            \App\Models\LeadStatusHistory::class,
        ];

        foreach ($auditableModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(AuditLogObserver::class);
            }
        }
        foreach ([\Illuminate\Notifications\DatabaseNotification::class, \App\Models\SystemNotification::class, \Spatie\Permission\Models\Role::class, \Spatie\Permission\Models\Permission::class] as $modelClass) {
            $modelClass::observe(AuditLogObserver::class);
        }
        config(['permission.events_enabled' => true]);
        foreach (['RoleAttached', 'RoleDetached', 'PermissionAttached', 'PermissionDetached'] as $eventName) {
            $eventClass = 'Spatie\\Permission\\Events\\'.$eventName;
            \Illuminate\Support\Facades\Event::listen($eventClass, function ($event) use ($eventName) {
                $role = str_starts_with($eventName, 'Role');
                $values = $role ? $event->rolesOrIds : $event->permissionsOrIds;
                $ids = collect($values instanceof \Illuminate\Support\Collection ? $values : (is_array($values) ? $values : [$values]))
                    ->map(fn ($value) => is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : $value)->filter(fn ($value) => is_scalar($value))->all();
                $table = $role ? 'roles' : 'permissions';
                $model = $event->model;
                $current = $model->{$table}()->pluck($table.'.id')->all();
                $attached = str_ends_with($eventName, 'Attached');
                $before = $model->auditAccessBefore[$table] ?? ($attached ? array_values(array_diff($current, $ids)) : array_values(array_unique(array_merge($current, $ids))));
                if ($before === $current) return;
                \App\Models\AuditLog::create(['user_id' => auth()->id(), 'action' => 'access.'.\Illuminate\Support\Str::snake($eventName), 'subject_type' => get_class($model), 'subject_id' => $model->id,
                    'organization_id' => $model->getAttribute('organization_id'), 'from_value' => [$table => $before], 'to_value' => [$table => $current],
                    'context' => ['category' => 'access', 'status' => 'success'], 'created_at' => now()]);
            });
        }

        // First Response SLA Observers
        foreach ([Lead::class, \App\Models\LeadAssignment::class, \App\Models\LeadReplacement::class,
            \App\Models\Meeting::class, SiteVisit::class, \App\Models\Project::class, \App\Models\Campaign::class] as $modelClass) {
            $modelClass::observe(\App\Observers\WebNotificationObserver::class);
        }
        Lead::observe(\App\Observers\LeadObserver::class);
        LeadCall::observe(FirstResponseObserver::class);
        LeadMessage::observe(FirstResponseObserver::class);
        LeadCommunication::observe(FirstResponseObserver::class);
        Lead::observe(FirstResponseObserver::class);

        // Real-Time Lead Scoring Observers
        Lead::observe(LeadScoreObserver::class);
        LeadCall::observe(LeadScoreObserver::class);
        LeadMessage::observe(LeadScoreObserver::class);
        LeadCommunication::observe(LeadScoreObserver::class);
        // Default Pagination Views
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.tailwind');
        \Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.tailwind');

        // Security & Auth Events Audit Subscriber
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\AuditAuthSubscriber::class);
    }
}
