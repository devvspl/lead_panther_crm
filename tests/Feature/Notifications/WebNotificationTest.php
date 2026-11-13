<?php
namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Organization, Lead, Project, Campaign, LeadSource, LeadAssignment, CreditWallet, Followup, LeadReplacement, ReplacementReason};
use App\Services\{NotificationDeliveryService, LeadDistributionService};
use App\Livewire\Shared\{NotificationBell, NotificationCenter};
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\{DB, Event, Notification};
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class WebNotificationTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $builder;
    private User $executive;
    private User $outsider;
    private Organization $org;
    private Project $project;
    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Super Admin', 'builder', 'sales-executive'] as $name) Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $this->org = Organization::create(['name' => 'Notification org', 'status' => 'active', 'delivery_timezone' => 'Asia/Kolkata', 'auto_assign_enabled' => false]);
        $other = Organization::create(['name' => 'Other org', 'status' => 'active']);
        $this->admin = User::factory()->create(['status' => 'active']); $this->admin->assignRole('Super Admin');
        $this->builder = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'active']); $this->builder->assignRole('builder');
        $this->executive = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'active']); $this->executive->assignRole('sales-executive');
        $this->outsider = User::factory()->create(['organization_id' => $other->id, 'status' => 'active']); $this->outsider->assignRole('builder');
        $source = LeadSource::create(['name' => 'Notification test source']);
        $this->project = Project::create(['client_id' => $this->builder->id, 'name' => 'Notification project', 'status' => 'active', 'delivery_daily_limit' => 1]);
        $this->campaign = Campaign::create(['project_id' => $this->project->id, 'lead_source_id' => $source->id, 'name' => 'Notification campaign', 'status' => 'active']);
        CreditWallet::create(['client_id' => $this->builder->id, 'balance' => 1000, 'opening_balance' => 1000]);
    }

    private function lead(): Lead
    {
        return Lead::create(['client_id' => $this->builder->id, 'campaign_id' => $this->campaign->id, 'lead_source_id' => $this->campaign->lead_source_id,
            'lead_code' => Lead::generateUniqueLeadCode(), 'name' => 'Notification lead', 'mobile' => '9000000000', 'status' => 'new', 'current_stage' => 'new']);
    }

    private function send(User $user, string $key = 'test-event', string $category = 'assignment', bool $important = false): void
    {
        app(NotificationDeliveryService::class)->sendWeb($user, 'test', 'Actual stored title', 'Actual stored message', $key, $category, $user->organization_id, null, null, $important);
    }

    public function test_successful_delivery_limit_and_queue_alerts_have_correct_recipients_and_no_duplicates(): void
    {
        $lead = $this->lead();
        $this->assertSame(0, $this->builder->notifications()->count());
        $this->assertTrue(app(LeadDistributionService::class)->releaseDelivery($lead->id));
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'lead_delivered')->count());
        $this->assertSame(1, $this->admin->notifications()->where('data->type', 'daily_limit_reached')->count());
        $this->assertSame(0, $this->outsider->notifications()->count());
        $this->assertSame(0, $this->executive->notifications()->count());
        $this->assertFalse(app(LeadDistributionService::class)->releaseDelivery($lead->id));
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'lead_delivered')->count());
        $this->lead(); $this->lead();
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'delivery_queued')->count());
    }

    public function test_notifications_roll_back_with_business_transaction(): void
    {
        try {
            DB::transaction(function () { $this->send($this->builder, 'rollback'); throw new \RuntimeException('Rollback'); });
        } catch (\RuntimeException $e) {}
        $this->assertSame(0, $this->builder->notifications()->count());
        $this->send($this->builder, 'retry'); $this->send($this->builder, 'retry');
        $this->assertSame(1, $this->builder->notifications()->count());
    }

    public function test_bell_read_actions_counts_and_polling_persist_without_leaking_other_users(): void
    {
        $this->send($this->builder, 'first'); $this->send($this->builder, 'second'); $this->send($this->outsider, 'third');
        $first = $this->builder->notifications()->first();
        $bell = Livewire::actingAs($this->builder)->test(NotificationBell::class)->assertViewHas('unreadCount', 2)
            ->call('toggleDropdown')->assertSee('Actual stored title')->assertSeeHtml('wire:poll.15s')
            ->call('markAsRead', $first->id)->assertViewHas('unreadCount', 1)->call('markAllAsRead')->assertViewHas('unreadCount', 0);
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($this->outsider->notifications()->first()->read_at);
        $this->send($this->builder, 'new-while-open');
        $bell->dispatch('notifications-refresh')->assertViewHas('unreadCount', 1);
        Livewire::actingAs($this->builder)->test(NotificationBell::class)->assertViewHas('unreadCount', 1);
    }

    public function test_other_users_notification_ids_cannot_be_read_or_opened(): void
    {
        $this->send($this->outsider);
        $id = $this->outsider->notifications()->first()->id;
        $this->actingAs($this->builder)->get(route('notifications.open', $id))->assertNotFound();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->builder)->test(NotificationBell::class)->call('markAsRead', $id);
    }

    public function test_preferences_persist_but_critical_admin_alerts_cannot_be_disabled(): void
    {
        Livewire::actingAs($this->executive)->test(NotificationCenter::class)->set('preferences.assignment', false)->call('savePreferences')->assertHasNoErrors()->assertDispatched('toast');
        $this->send($this->executive->fresh(), 'disabled');
        $this->assertSame(0, $this->executive->notifications()->count());
        $this->admin->update(['notification_preferences' => ['delivery' => false]]);
        app(NotificationDeliveryService::class)->sendWeb($this->admin, 'delivery_failed', 'Critical alert', 'Needs attention', 'critical', 'delivery', $this->org->id, null, null, true);
        $this->assertSame(1, $this->admin->notifications()->count());
        Livewire::actingAs($this->executive)->test(NotificationCenter::class)->assertSet('preferences.assignment', false);
    }

    public function test_lead_links_are_resolved_from_database_and_revoked_access_is_denied(): void
    {
        $lead = $this->lead(); app(LeadDistributionService::class)->releaseDelivery($lead->id);
        $notice = $this->builder->notifications()->where('data->type', 'lead_delivered')->first();
        $this->actingAs($this->builder)->get(route('notifications.open', $notice->id))
            ->assertRedirect(route('leads.index', ['search' => $lead->lead_code, 'notification_lead' => $lead->id]));
        $this->assertNotNull($notice->fresh()->read_at);
        $lead->update(['client_id' => $this->outsider->id]);
        $this->get(route('notifications.open', $notice->id))->assertNotFound();
    }

    public function test_private_channel_authentication_allows_only_the_authenticated_user(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        require base_path('routes/channels.php');
        $this->actingAs($this->builder)->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-App.Models.User.'.$this->builder->id])->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-App.Models.User.'.$this->outsider->id])->assertForbidden();
    }

    public function test_assignment_replacement_and_activity_events_use_real_records(): void
    {
        $lead = $this->lead(); app(LeadDistributionService::class)->releaseDelivery($lead->id);
        app(LeadDistributionService::class)->assignLeadToUser($lead->fresh(), $this->executive, 'manual');
        $this->assertSame(1, $this->executive->notifications()->where('data->type', 'lead_assigned')->count());
        event(new \App\Events\FirstResponseRecorded($lead->fresh(), now()->toDateTimeString(), 'call'));
        $this->assertSame(1, $this->executive->notifications()->where('data->type', 'first_response')->count());
        $reason = ReplacementReason::create(['label' => 'Wrong contact', 'is_eligible' => true]);
        $request = LeadReplacement::create(['lead_id' => $lead->id, 'reason_id' => $reason->id, 'requested_by' => $this->builder->id, 'requested_at' => now(), 'status' => 'pending']);
        $request->update(['status' => 'rejected']);
        $this->assertSame(1, $this->admin->notifications()->where('data->type', 'replacement_submitted')->count());
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'replacement_rejected')->count());
    }

    public function test_due_reminders_sla_and_daily_summaries_do_not_repeat_on_scheduler_retries(): void
    {
        Notification::fake();
        $lead = $this->lead(); app(LeadDistributionService::class)->releaseDelivery($lead->id);
        $lead->update(['assigned_to' => $this->executive->id]);
        Followup::create(['lead_id' => $lead->id, 'due_at' => now()->subMinute(), 'status' => 'pending', 'created_by' => $this->executive->id]);
        $this->artisan('followups:check-due')->assertSuccessful(); $this->artisan('followups:check-due')->assertSuccessful();
        $this->assertSame(1, $this->executive->notifications()->where('data->type', 'followup_due')->count());
        $this->travel(20)->minutes();
        $this->artisan('notifications:check-operational')->assertSuccessful(); $this->artisan('notifications:check-operational')->assertSuccessful();
        $this->assertSame(1, $this->executive->notifications()->where('data->type', 'sla_breached')->count());
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'daily_summary')->count());
        $this->assertSame(0, $this->outsider->notifications()->where('organization_id', $this->org->id)->count());
    }

    public function test_configuration_pause_resume_and_failed_delivery_alerts_are_persisted(): void
    {
        $this->project->update(['delivery_enabled' => false]);
        $this->project->update(['delivery_enabled' => true]);
        $this->campaign->update(['delivery_daily_limit' => 3]);
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'campaign_paused')->count());
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'campaign_resumed')->count());
        $this->assertSame(3, $this->admin->notifications()->where('data->type', 'configuration_updated')->count());
        CreditWallet::where('client_id', $this->builder->id)->update(['balance' => 0]);
        $lead = $this->lead();
        $this->assertFalse(app(LeadDistributionService::class)->releaseDelivery($lead->id));
        $this->assertFalse(app(LeadDistributionService::class)->releaseDelivery($lead->id));
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'delivery_failed')->count());
        $this->assertSame(0, $this->builder->notifications()->where('data->type', 'lead_delivered')->count());
        CreditWallet::where('client_id', $this->builder->id)->update(['balance' => 1000]);
        $this->assertTrue(app(LeadDistributionService::class)->releaseDelivery($lead->id));
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'lead_delivered')->count());
    }

    public function test_meetings_visits_closed_won_lost_and_direct_reassignments_notify_relevant_users(): void
    {
        $lead = $this->lead(); app(LeadDistributionService::class)->releaseDelivery($lead->id);
        $lead->refresh()->update(['assigned_to' => $this->executive->id]);
        $this->assertSame(1, $this->executive->notifications()->where('data->type', 'lead_assigned')->count());
        \App\Models\Meeting::create(['lead_id' => $lead->id, 'scheduled_at' => now()->addDay(), 'location' => 'Site office']);
        \App\Models\SiteVisit::create(['lead_id' => $lead->id, 'visit_date' => now()->addDays(2), 'executive_id' => $this->executive->id]);
        $this->assertSame(2, $this->executive->notifications()->where('data->type', 'activity_scheduled')->count());
        $lead->update(['current_stage' => 'closed_won']);
        $lead->update(['current_stage' => 'lost']);
        $this->assertSame(1, $this->executive->notifications()->where('data->type', 'lead_closed_won')->count());
        $this->assertSame(1, $this->executive->notifications()->where('data->type', 'lead_lost')->count());
        $other = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'active']); $other->assignRole('sales-executive');
        $lead->update(['assigned_to' => $other->id]);
        $this->assertSame(1, $other->notifications()->where('data->type', 'lead_assigned')->count());
        $this->assertSame(1, $this->executive->notifications()->where('data->type', 'lead_reassigned')->count());
        $this->assertSame(0, $this->outsider->notifications()->where('organization_id', $this->org->id)->count());
    }

    public function test_actual_replacement_approval_and_generation_emit_each_update_once(): void
    {
        $lead = $this->lead(); app(LeadDistributionService::class)->releaseDelivery($lead->id);
        $reason = ReplacementReason::create(['label' => 'Invalid contact', 'is_eligible' => true]);
        $request = LeadReplacement::create(['lead_id' => $lead->id, 'reason_id' => $reason->id, 'requested_by' => $this->builder->id, 'requested_at' => now(), 'status' => 'pending']);
        $this->assertTrue($request->approve($this->admin->id));
        $this->assertNotNull($request->fresh()->replacement_lead_id);
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'replacement_approved')->count());
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'replacement_generated')->count());
    }

    public function test_history_paginates_and_account_organization_changes_hide_old_notifications(): void
    {
        for ($i = 0; $i < 23; $i++) $this->send($this->builder, 'page-'.$i);
        $page = Livewire::actingAs($this->builder)->test(NotificationCenter::class)
            ->assertViewHas('notifications', fn ($items) => $items->count() === 20 && $items->total() === 23)
            ->call('setPage', 2)->assertViewHas('notifications', fn ($items) => $items->count() === 3);
        $this->builder->update(['organization_id' => $this->outsider->organization_id]);
        Livewire::actingAs($this->builder)->test(NotificationBell::class)->assertViewHas('unreadCount', 0);
    }

    public function test_broadcast_failure_preserves_the_database_inbox_for_polling(): void
    {
        Event::listen(\App\Events\WebNotificationsChanged::class, function () { throw new \RuntimeException('WebSocket unavailable'); });
        DB::transaction(fn () => $this->send($this->builder, 'offline'));
        Livewire::actingAs($this->builder)->test(NotificationBell::class)->assertViewHas('unreadCount', 1);
        $this->assertSame(1, $this->builder->notifications()->count());
    }

    public function test_rapid_direct_reassignment_back_to_previous_agent_is_not_suppressed(): void
    {
        $this->travelTo(now()->startOfSecond());
        $lead = $this->lead(); app(LeadDistributionService::class)->releaseDelivery($lead->id);
        app(LeadDistributionService::class)->assignLeadToUser($lead->fresh(), $this->executive, 'manual');
        $other = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'active']); $other->assignRole('sales-executive');
        $lead->refresh()->update(['assigned_to' => $other->id]);
        $lead->update(['assigned_to' => $this->executive->id]);
        $this->assertSame(2, $this->executive->notifications()->where('data->type', 'lead_assigned')->count());
        $this->assertSame(1, $other->notifications()->where('data->type', 'lead_reassigned')->count());
    }

    public function test_existing_crm_delivery_channel_creates_a_real_inbox_entry_and_retries_do_not_duplicate_it(): void
    {
        $service = app(NotificationDeliveryService::class);
        $log = $service->send('crm', $this->builder->email, 'lead_status_changed', ['lead_code' => 'LP-TEST-CRM', 'new_status' => 'contacted'], $this->builder);
        $this->assertSame('delivered', $log->status);
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'crm_message')->count());
        $service->retry($log->id);
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'crm_message')->count());
    }

    public function test_legacy_notification_migration_uses_the_source_organization_not_the_recipient_organization(): void
    {
        $lead = $this->lead();
        $unscopedUser = User::factory()->create(['organization_id' => null, 'status' => 'active']); $unscopedUser->assignRole('sales-executive');
        $migration = require base_path('database/migrations/2026_11_13_100000_extend_web_notifications.php');
        $migration->down();
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('notifications')->insert(['id' => $id, 'type' => \App\Notifications\LeadAssignedNotification::class,
            'notifiable_type' => User::class, 'notifiable_id' => $this->outsider->id,
            'data' => json_encode(['lead_id' => $lead->id, 'title' => 'Legacy']), 'created_at' => now(), 'updated_at' => now()]);
        $unknownId = (string) \Illuminate\Support\Str::uuid();
        DB::table('notifications')->insert(['id' => $unknownId, 'type' => 'legacy', 'notifiable_type' => User::class,
            'notifiable_id' => $unscopedUser->id, 'data' => json_encode(['title' => 'Unverifiable legacy alert']), 'created_at' => now(), 'updated_at' => now()]);
        $migration->up();
        $this->assertEquals($this->org->id, DB::table('notifications')->where('id', $id)->value('organization_id'));
        $this->assertSame(0, app(NotificationDeliveryService::class)->inbox($this->outsider)->whereKey($id)->count());
        $this->assertSame(0, app(NotificationDeliveryService::class)->inbox($unscopedUser)->whereKey($unknownId)->count());
    }

    public function test_stale_notification_routes_do_not_break_bell_rendering_or_secure_navigation(): void
    {
        $lead = $this->lead(); app(LeadDistributionService::class)->releaseDelivery($lead->id);
        $notice = $this->builder->notifications()->where('data->type', 'lead_delivered')->firstOrFail();
        $routes = new \Illuminate\Routing\RouteCollection;
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if (! in_array($route->getName(), ['notifications.open', 'notifications.index'])) $routes->add($route);
        }
        app('router')->setRoutes($routes); app('url')->setRoutes($routes);
        Livewire::actingAs($this->builder)->test(NotificationBell::class)->call('toggleDropdown')
            ->assertSee('New lead delivered')->call('markAsReadAndNavigate', $notice->id)
            ->assertRedirect(route('leads.index', ['search' => $lead->lead_code, 'notification_lead' => $lead->id]));
        $this->assertNotNull($notice->fresh()->read_at);
        $this->send($this->builder, 'no-entity-fallback');
        $generic = $this->builder->notifications()->where('data->type', 'test')->firstOrFail();
        Livewire::actingAs($this->builder)->test(NotificationBell::class)->call('markAsReadAndNavigate', $generic->id)->assertRedirect(route('dashboard'));
        $this->send($this->outsider, 'not-yours');
        $other = $this->outsider->notifications()->firstOrFail();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->builder)->test(NotificationBell::class)->call('markAsReadAndNavigate', $other->id);
    }
}
