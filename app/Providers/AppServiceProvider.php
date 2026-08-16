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

        Lead::observe(AuditLogObserver::class);
        CreditTransaction::observe(AuditLogObserver::class);
        LeadReplacement::observe(AuditLogObserver::class);
        User::observe(AuditLogObserver::class);

        // First Response SLA Observers
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
    }
}
