<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Bus, Event};
use App\Models\{User, Organization};
use App\Jobs\SendNotificationEmail;
use App\Events\WebNotificationsChanged;
use App\Services\NotificationDeliveryService;

class EmailQueueDispatchTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        // This test needs real commits, unlike the transaction-wrapped inbox tests.
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$inMemoryConnections = [];
        $this->beforeApplicationDestroyed(function () {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    public function test_email_is_dispatched_after_commit_once_and_not_on_rollback(): void
    {
        Bus::fake([SendNotificationEmail::class, \App\Jobs\SendBrowserPush::class]);
        config(['webpush.enabled' => true, 'webpush.public_key' => 'test-public', 'webpush.private_key' => 'test-private']);
        Event::fake([WebNotificationsChanged::class]);
        $user = User::factory()->create(['status' => 'active', 'organization_id' => Organization::create(['name' => 'Queue org'])->id]);
        \App\Models\BrowserPushSubscription::create(['user_id' => $user->id, 'organization_id' => $user->organization_id,
            'endpoint_hash' => hash('sha256', 'test-browser'), 'subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/test-browser']]);
        $send = fn ($key) => app(NotificationDeliveryService::class)->sendWeb($user, 'lead_assigned', 'Assigned', 'Lead assigned.', $key, 'assignment', $user->organization_id);
        DB::beginTransaction();
        $send('rollback');
        Bus::assertNotDispatched(SendNotificationEmail::class);
        DB::rollBack();
        $this->assertDatabaseCount('notifications', 0);
        Bus::assertNotDispatched(SendNotificationEmail::class);
        Bus::assertNotDispatched(\App\Jobs\SendBrowserPush::class);
        DB::transaction(function () use ($send) {
            $send('committed');
            Bus::assertNotDispatched(SendNotificationEmail::class);
        });
        Bus::assertDispatchedTimes(SendNotificationEmail::class, 1);
        Bus::assertDispatchedTimes(\App\Jobs\SendBrowserPush::class, 1);
        $send('committed');
        Bus::assertDispatchedTimes(SendNotificationEmail::class, 1);
        Bus::assertDispatchedTimes(\App\Jobs\SendBrowserPush::class, 1);
        $this->assertDatabaseCount('notifications', 1);
    }
}
