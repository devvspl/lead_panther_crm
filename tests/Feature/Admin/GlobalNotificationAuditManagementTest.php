<?php
namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Route, Event};
use App\Models\{User, Organization, Project, Lead, LeadSource, Campaign, AuditLog, SystemNotification, GeneralSetting};
use App\Services\NotificationDeliveryService;
use App\Livewire\Shared\{NotificationCenter, NotificationBell};
use App\Livewire\Admin\AuditLogBrowser;
use Livewire\Livewire;
use Spatie\Permission\Models\{Role, Permission};
use Maatwebsite\Excel\Facades\Excel;

class GlobalNotificationAuditManagementTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $builder;
    private User $other;
    private Organization $org;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Super Admin', 'builder', 'sales-executive'] as $name) Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $this->org = Organization::create(['name' => 'Audit organization', 'status' => 'active']);
        $otherOrg = Organization::create(['name' => 'Other organization', 'status' => 'active']);
        $this->admin = User::factory()->create(['name' => 'Audit administrator', 'status' => 'active']); $this->admin->assignRole('Super Admin');
        $this->builder = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'active']); $this->builder->assignRole('builder');
        $this->other = User::factory()->create(['organization_id' => $otherOrg->id, 'status' => 'active']); $this->other->assignRole('sales-executive');
        $this->project = Project::create(['client_id' => $this->builder->id, 'name' => 'Audit project', 'status' => 'active', 'delivery_daily_limit' => 0, 'delivery_interval_minutes' => 0]);
    }

    private function notice(User $user, string $type, string $key, string $message): SystemNotification
    {
        app(NotificationDeliveryService::class)->sendWeb($user, $type, $message, $message, $key, 'delivery', $user->organization_id);
        return SystemNotification::where('event_key', hash('sha256', $user->id.'|'.$key))->firstOrFail();
    }

    public function test_super_admin_global_filters_and_read_actions_keep_the_bell_personal(): void
    {
        $a = $this->notice($this->builder, 'delivery_failed', 'a', 'Alpha delivery');
        $b = $this->notice($this->other, 'followup_due', 'b', 'Beta reminder');
        $component = Livewire::actingAs($this->admin)->test(NotificationCenter::class)
            ->assertSee('System Notifications')->assertViewHas('notifications', fn ($items) => $items->total() === 2)
            ->assertSee($this->other->name)->set('organizationId', $this->org->id)
            ->assertViewHas('notifications', fn ($items) => $items->total() === 1)->assertDontSee('Beta reminder')
            ->call('resetFilters')->set('roleFilter', 'sales-executive')->assertSee('Beta reminder')->assertDontSee('Alpha delivery')
            ->call('resetFilters')->set('typeFilter', 'delivery_failed')->assertSee('Alpha delivery')->assertDontSee('Beta reminder')
            ->call('resetFilters')->set('recipientId', $this->other->id)->call('markAsRead', $b->id);
        $this->assertNotNull($b->fresh()->read_at);
        $this->assertNull($a->fresh()->read_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.read', 'user_id' => $this->admin->id]);
        $component->call('resetFilters')->set('readFilter', 'read')->assertViewHas('notifications', fn ($items) => $items->total() === 1)
            ->call('resetFilters')->set('search', 'Alpha')->assertViewHas('notifications', fn ($items) => $items->total() === 1)
            ->call('inspect', $a->id)->assertSee('Alpha delivery')->call('closeInspection');
        Livewire::actingAs($this->admin)->test(NotificationBell::class)->assertViewHas('unreadCount', 0);
    }

    public function test_normal_users_cannot_expand_their_inbox_or_open_other_organizations_logs(): void
    {
        $this->notice($this->builder, 'own', 'own', 'Own authorized notification');
        $foreign = $this->notice($this->other, 'foreign', 'foreign', 'Foreign secret message');
        Livewire::actingAs($this->builder)->test(NotificationCenter::class)->set('organizationId', $this->other->organization_id)
            ->set('recipientId', $this->other->id)->assertSee('Own authorized notification')->assertDontSee('Foreign secret message');
        $this->actingAs($this->builder)->get(route('admin.audit-logs'))->assertForbidden();
        $this->get(route('notifications.open', $foreign->id))->assertNotFound();
        Livewire::actingAs($this->builder)->test(AuditLogBrowser::class)->assertForbidden();
    }

    public function test_project_configuration_diff_actor_snapshot_and_role_permission_changes_are_recorded(): void
    {
        $this->actingAs($this->admin);
        $this->project->update(['delivery_daily_limit' => 25, 'delivery_interval_minutes' => 15]);
        $log = AuditLog::where('subject_type', Project::class)->where('subject_id', $this->project->id)->where('action', 'project.updated')->latest('id')->firstOrFail();
        $this->assertSame(0, json_decode($log->from_value, true)['delivery_daily_limit']);
        $this->assertSame(25, json_decode($log->to_value, true)['delivery_daily_limit']);
        $this->assertEquals($this->org->id, $log->organization_id);
        $this->assertSame($this->admin->email, $log->context['actor']['email']);
        $this->assertContains('Super Admin', $log->context['actor']['roles']);
        $this->builder->assignRole('sales-executive');
        $permission = Permission::create(['name' => 'inspect-test', 'guard_name' => 'web']);
        $this->builder->givePermissionTo($permission);
        $this->assertDatabaseHas('audit_logs', ['action' => 'access.role_attached', 'subject_id' => $this->builder->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'access.permission_attached', 'subject_id' => $this->builder->id]);
        $replacement = Permission::create(['name' => 'replace-test', 'guard_name' => 'web']);
        $this->builder->syncPermissions($replacement);
        $synced = AuditLog::where('action', 'access.permissions_synced')->where('subject_id', $this->builder->id)->latest('id')->firstOrFail();
        $this->assertEquals([$permission->id], json_decode($synced->from_value, true)['permissions']);
        $this->assertEquals([$replacement->id], json_decode($synced->to_value, true)['permissions']);
    }

    public function test_append_only_records_reject_instance_and_bulk_mutation(): void
    {
        $log = AuditLog::create(['action' => 'test.immutable', 'created_at' => now(), 'to_value' => ['value' => 'Original']]);
        foreach ([fn () => $log->update(['action' => 'tampered']), fn () => $log->delete(),
            fn () => AuditLog::whereKey($log->id)->update(['action' => 'tampered']), fn () => AuditLog::whereKey($log->id)->delete(), fn () => AuditLog::truncate()] as $mutation) {
            try { $mutation(); $this->fail('Audit modification must be blocked.'); }
            catch (\LogicException $e) { $this->assertStringContainsString('append-only', $e->getMessage()); }
        }
        $this->assertSame('test.immutable', AuditLog::findOrFail($log->id)->action);
    }

    public function test_sensitive_values_are_masked_in_storage_inspection_copy_data_and_export(): void
    {
        $this->actingAs($this->admin);
        $log = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'test.redaction', 'organization_id' => $this->org->id,
            'from_value' => ['password' => 'old-secret'], 'to_value' => ['password' => 'new-secret', 'nested' => ['api_key' => 'api-secret', 'otp' => '123456'], 'blob' => json_encode(['refresh_token' => 'refresh-secret'])],
            'context' => ['payload' => ['Authorization' => 'Bearer auth-secret']], 'created_at' => now()]);
        $raw = DB::table('audit_logs')->where('id', $log->id)->first();
        foreach (['old-secret', 'new-secret', 'api-secret', '123456', 'refresh-secret', 'auth-secret'] as $secret) $this->assertStringNotContainsString($secret, json_encode($raw));
        $legacyId = DB::table('audit_logs')->insertGetId(['action' => 'test.legacy-redaction', 'to_value' => json_encode(['client_secret' => 'legacy-secret']), 'created_at' => now()]);
        $component = Livewire::actingAs($this->admin)->test(AuditLogBrowser::class)->call('viewDetails', $legacyId)
            ->assertSee('Basic Information')->assertSee('Request Details')->assertSee('Change Details')->assertSee('System Details')->assertDontSee('legacy-secret');
        $this->assertStringNotContainsString('legacy-secret', $component->instance()->formatPayload($component->instance()->inspection));
        Excel::fake();
        $component->set('search', 'test.legacy-redaction')->call('exportExcel');
        Excel::assertDownloaded('audit-logs_'.now()->format('Y-m-d').'.xlsx', function ($export) {
            foreach ($export->collection() as $entry) $this->assertStringNotContainsString('legacy-secret', json_encode($export->map($entry)));
            return true;
        });
    }

    public function test_filtered_audit_navigation_and_global_notification_export_use_current_filters(): void
    {
        $this->actingAs($this->admin);
        $a = AuditLog::create(['action' => 'test.navigation.a', 'organization_id' => $this->org->id, 'created_at' => now()]);
        AuditLog::create(['action' => 'test.navigation.foreign', 'organization_id' => $this->other->organization_id, 'created_at' => now()]);
        $b = AuditLog::create(['action' => 'test.navigation.b', 'organization_id' => $this->org->id, 'created_at' => now()]);
        Livewire::test(AuditLogBrowser::class)->set('search', 'test.navigation')->set('organizationId', $this->org->id)
            ->call('viewDetails', $a->id)->call('navigateRecord', 'next')->assertSet('selectedLog.id', $b->id)->call('navigateRecord', 'previous')->assertSet('selectedLog.id', $a->id);
        $this->notice($this->builder, 'alpha', 'export-a', 'Included export');
        $this->notice($this->other, 'beta', 'export-b', 'Excluded export');
        Excel::fake();
        Livewire::test(NotificationCenter::class)->set('organizationId', $this->org->id)->call('exportExcel');
        Excel::assertDownloaded('system-notifications.xlsx', function ($export) { $this->assertCount(1, $export->collection()); return true; });
    }

    public function test_request_details_mask_route_tokens_query_and_nested_payload(): void
    {
        Route::middleware('web')->post('/audit-probe/{token}', fn () => response()->json(['ok' => true]))->name('audit.probe');
        $this->actingAs($this->admin)->postJson('/audit-probe/route-secret?api_key=query-secret', ['password' => 'request-secret', 'nested' => ['otp' => '654321'], 'name' => 'Real request'])->assertOk();
        $log = AuditLog::where('action', 'route.audit.probe')->latest('id')->firstOrFail();
        $this->assertSame('POST', $log->context['http_method']);
        $this->assertNotEmpty($log->context['request_id']);
        foreach (['route-secret', 'query-secret', 'request-secret', '654321'] as $secret) $this->assertStringNotContainsString($secret, json_encode([$log->context, $log->from_value, $log->to_value]));
        $this->assertStringContainsString('Real request', $log->to_value);
    }

    public function test_global_pagination_date_filters_and_filtered_read_all_are_database_driven(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        $this->travel(-1)->days();
        $old = $this->notice($this->builder, 'old', 'yesterday', 'Yesterday notice');
        $this->travel(1)->days();
        for ($i = 0; $i < 24; $i++) $this->notice($this->builder, 'today', 'today-'.$i, 'Today notice '.$i);
        $page = Livewire::actingAs($this->admin)->test(NotificationCenter::class)->set('perPage', 10)
            ->assertViewHas('notifications', fn ($rows) => $rows->count() === 10 && $rows->total() === 25)
            ->call('setPage', 3)->assertViewHas('notifications', fn ($rows) => $rows->count() === 5)
            ->set('dateFrom', now()->toDateString())->set('dateTo', now()->toDateString())->call('applyFilters')
            ->assertViewHas('notifications', fn ($rows) => $rows->total() === 24)->call('markAllAsRead');
        $this->assertNull($old->fresh()->read_at);
        $this->assertSame(24, SystemNotification::whereNotNull('read_at')->count());
        $audit = AuditLog::where('action', 'notification.read_all')->latest('id')->firstOrFail();
        $this->assertSame(24, json_decode($audit->to_value, true)['count']);
    }

    public function test_public_webhook_requests_and_failed_responses_are_recorded_with_masked_errors(): void
    {
        Route::middleware('api')->post('/api/webhooks/audit-check', fn () => response()->json(['accepted' => true]))->name('api.webhooks.audit-check');
        $this->postJson('/api/webhooks/audit-check', ['access_token' => 'webhook-secret', 'lead_id' => 99])->assertOk();
        $webhook = AuditLog::where('action', 'route.api.webhooks.audit-check')->latest('id')->firstOrFail();
        $this->assertSame('webhook', $webhook->context['category']);
        $this->assertStringNotContainsString('webhook-secret', $webhook->to_value);
        Route::middleware('web')->post('/audit-failure-check', fn () => response()->json(['message' => 'token=error-secret'], 422))->name('audit.failure-check');
        $this->actingAs($this->admin)->postJson('/audit-failure-check')->assertStatus(422);
        $failed = AuditLog::where('action', 'route.audit.failure-check')->latest('id')->firstOrFail();
        $this->assertSame('failure', $failed->context['status']);
        $this->assertSame(422, $failed->context['http_status']);
        $this->assertStringNotContainsString('error-secret', $failed->to_value);
    }
}
