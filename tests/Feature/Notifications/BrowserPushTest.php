<?php
namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Event};
use App\Models\{User, Organization, BrowserPushSubscription, BrowserPushDelivery};
use App\Services\{BrowserPushService, NotificationDeliveryService};
use App\Jobs\{SendBrowserPush, SendNotificationEmail};
use Spatie\Permission\Models\Role;

class BrowserPushTest extends TestCase {
    use RefreshDatabase;
    private User $user;
    private User $other;
    protected function setUp(): void {
        parent::setUp();
        config(['webpush.enabled' => true, 'webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key']);
        $org = Organization::create(['name' => 'Push org']);
        $this->user = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        Role::firstOrCreate(['name' => 'Team Leader', 'guard_name' => 'web']);
        $this->user->assignRole('Team Leader');
        $this->other = User::factory()->create(['organization_id' => Organization::create(['name' => 'Other push org'])->id, 'status' => 'active']);
        Bus::fake([SendBrowserPush::class, SendNotificationEmail::class]);
        Event::fake([\App\Events\WebNotificationsChanged::class]);
    }
    private function data(): array {
        return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/test-browser', 'keys' => [
            'p256dh' => rtrim(strtr(base64_encode(chr(4).str_repeat('x', 64)), '+/', '-_'), '='),
            'auth' => rtrim(strtr(base64_encode(str_repeat('a', 16)), '+/', '-_'), '='),
        ]];
    }
    private function subscribe(): BrowserPushSubscription {
        $this->actingAs($this->user)->postJson(route('notifications.push.subscribe'), $this->data())->assertOk();
        return BrowserPushSubscription::firstOrFail();
    }
    private function notification() {
        $record = app(NotificationDeliveryService::class)->sendWeb($this->user, 'lead_assigned', 'Assigned', 'Private team message.', 'push-event', 'assignment', $this->user->organization_id);
        return \App\Models\SystemNotification::findOrFail($record->id);
    }
    public function test_team_leader_can_subscribe_with_encrypted_keys_and_disable_only_own_browser(): void {
        $record = $this->subscribe();
        $this->assertSame($this->data()['endpoint'], $record->subscription['endpoint']);
        $this->assertStringNotContainsString('fcm.googleapis.com', DB::table('browser_push_subscriptions')->value('subscription'));
        $this->getJson(route('notifications.push.config'))->assertOk()->assertJson(['configured' => true])->assertDontSee('test-private-key');
        $this->actingAs($this->other)->postJson(route('notifications.push.unsubscribe'), ['endpoint' => $this->data()['endpoint']])->assertNotFound();
        $this->postJson(route('notifications.push.subscribe'), $this->data())->assertStatus(409);
        $this->actingAs($this->user)->postJson(route('notifications.push.unsubscribe'), ['endpoint' => $this->data()['endpoint']])->assertOk();
        $this->assertDatabaseCount('browser_push_subscriptions', 0);
    }
    public function test_authenticated_layout_loads_push_controls_independently_of_vite(): void {
        $this->actingAs($this->user)->get('/notifications')->assertOk()->assertSee('browser-push.js?v=', false)
            ->assertSee('Enable browser notifications?')->assertSee('Not Now');
    }
    public function test_subscription_rejects_private_endpoints_and_invalid_keys(): void {
        $data = $this->data(); $data['endpoint'] = 'https://127.0.0.1/private';
        $this->actingAs($this->user)->postJson(route('notifications.push.subscribe'), $data)->assertStatus(422);
        $data = $this->data(); $data['keys']['auth'] = 'invalid';
        $this->postJson(route('notifications.push.subscribe'), $data)->assertStatus(422);
        $this->assertDatabaseCount('browser_push_subscriptions', 0);
    }
    public function test_private_message_requires_current_recipient_session_and_organization(): void {
        $record = $this->notification();
        $url = route('notifications.push.message', $record->id);
        $this->actingAs($this->user)->getJson($url)->assertOk()->assertJson(['body' => 'Private team message.']);
        $this->actingAs($this->other)->getJson($url)->assertNotFound();
        $this->user->update(['organization_id' => $this->other->organization_id]);
        $this->actingAs($this->user)->getJson($url)->assertNotFound();
    }
    public function test_job_is_idempotent_and_logs_provider_acceptance(): void {
        $subscription = $this->subscribe(); $record = $this->notification();
        $service = \Mockery::mock(BrowserPushService::class)->makePartial();
        $service->shouldReceive('send')->once()->andReturn(['status' => 'sent', 'retry' => false]);
        $job = new SendBrowserPush($record->id, $subscription->id);
        $job->handle($service); $job->handle($service);
        $this->assertDatabaseHas('browser_push_deliveries', ['notification_id' => $record->id, 'status' => 'sent', 'attempts' => 1]);
    }
    public function test_expired_subscription_is_removed_and_failure_is_retryable(): void {
        $subscription = $this->subscribe(); $record = $this->notification();
        $service = \Mockery::mock(BrowserPushService::class)->makePartial();
        $service->shouldReceive('send')->once()->andReturn(['status' => 'expired', 'retry' => false]);
        (new SendBrowserPush($record->id, $subscription->id))->handle($service);
        $this->assertDatabaseCount('browser_push_subscriptions', 0);
        $this->assertDatabaseHas('browser_push_deliveries', ['notification_id' => $record->id, 'status' => 'expired']);
    }
    public function test_temporary_provider_failure_does_not_claim_success(): void {
        $subscription = $this->subscribe(); $record = $this->notification();
        $service = \Mockery::mock(BrowserPushService::class)->makePartial();
        $service->shouldReceive('send')->once()->andReturn(['status' => 'failed', 'retry' => true]);
        try { (new SendBrowserPush($record->id, $subscription->id))->handle($service); $this->fail('Expected retryable failure'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Temporary', $e->getMessage()); }
        $this->assertDatabaseHas('browser_push_deliveries', ['notification_id' => $record->id, 'status' => 'failed', 'attempts' => 1]);
    }
    public function test_test_button_calls_push_now_without_email_or_worker(): void {
        $this->subscribe();
        $service = \Mockery::mock(BrowserPushService::class)->makePartial();
        $service->shouldReceive('send')->once()->andReturn(['status' => 'sent', 'retry' => false]);
        $this->app->instance(BrowserPushService::class, $service);
        $this->postJson(route('notifications.push.test'), ['endpoint' => $this->data()['endpoint']])->assertOk()->assertJsonPath('message', 'Test accepted by the browser push provider. Check your device notifications.');
        Bus::assertNotDispatched(SendNotificationEmail::class);
        Bus::assertNotDispatched(SendBrowserPush::class);
    }
    public function test_logout_removes_only_the_current_browser_subscription(): void {
        $record = $this->subscribe();
        $second = BrowserPushSubscription::create(['user_id' => $this->user->id, 'organization_id' => $this->user->organization_id,
            'endpoint_hash' => hash('sha256', 'second-browser'), 'subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/other-device']]);
        $this->post('/logout')->assertRedirect('/');
        $this->assertDatabaseMissing('browser_push_subscriptions', ['id' => $record->id]);
        $this->assertDatabaseHas('browser_push_subscriptions', ['id' => $second->id]);
    }
    public function test_real_encryption_and_provider_response_handling_use_no_external_network(): void {
        app(BrowserPushService::class)->prepareCrypto();
        try { $serverKeys = \Minishlink\WebPush\VAPID::createVapidKeys(); $browserKeys = \Minishlink\WebPush\VAPID::createVapidKeys(); }
        catch (\Throwable $e) { $this->markTestSkipped('PHP OpenSSL P-256 configuration must be available at process startup.'); }
        config(['webpush.public_key' => $serverKeys['publicKey'], 'webpush.private_key' => $serverKeys['privateKey'], 'webpush.subject' => 'mailto:admin@example.com']);
        $subscription = BrowserPushSubscription::create(['user_id' => $this->user->id, 'organization_id' => $this->user->organization_id,
            'endpoint_hash' => hash('sha256', 'encrypted-browser'), 'subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/encryption-test',
                'keys' => ['p256dh' => $browserKeys['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]]]);
        $history = [];
        $handler = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(201), new \GuzzleHttp\Psr7\Response(410),
        ]));
        $handler->push(\GuzzleHttp\Middleware::history($history));
        $service = \Mockery::mock(BrowserPushService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('httpClient')->twice()->andReturn(new \GuzzleHttp\Client(['handler' => $handler]));
        $record = $this->notification();
        $this->assertSame('sent', $service->send($subscription, $record)['status']);
        $this->assertSame('expired', $service->send($subscription, $record)['status']);
        $this->assertNotEmpty((string) $history[0]['request']->getBody());
        $this->assertStringNotContainsString('Private team message', (string) $history[0]['request']->getBody());
        $this->assertTrue($history[0]['request']->hasHeader('Authorization'));
    }
}
