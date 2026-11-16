<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Bus, DB};
use App\Models\{User, Organization, GeneralSetting, NotificationLog, SystemNotification};
use App\Services\NotificationDeliveryService;
use App\Jobs\SendNotificationEmail;
use App\Mail\BrandedNotificationMail;
use App\Livewire\Settings\EmailNotificationSettings;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;
    private User $user;
    protected function setUp(): void {
        parent::setUp();
        $org = Organization::create(['name' => 'Email Org']);
        $this->user = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        config(['mail.default' => 'smtp']);
        Bus::fake([SendNotificationEmail::class]);
    }
    private function notification(): SystemNotification {
        app(NotificationDeliveryService::class)->sendWeb($this->user, 'lead_assigned', 'New lead assigned', 'Your lead is ready.', 'email-event-1', 'assignment', $this->user->organization_id);
        return SystemNotification::where('notifiable_id', $this->user->id)->firstOrFail();
    }
    public function test_email_sends_once_with_recipient_scope_and_persistent_log(): void {
        Mail::fake();
        $record = $this->notification();
        $job = new SendNotificationEmail($record->id);
        $job->handle(); $job->handle();
        Mail::assertSent(BrandedNotificationMail::class, fn($mail) => $mail->hasTo($this->user->email) && str_contains($mail->actionUrl, $record->id));
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('notification_logs', ['email_notification_id' => $record->id, 'status' => 'sent']);
        $this->notification();
        $this->assertSame(1, SystemNotification::count());
    }
    public function test_disabled_email_and_wrong_organization_do_not_send(): void {
        Mail::fake(); $record = $this->notification();
        $this->user->update(['organization_id' => Organization::create(['name' => 'Other Org'])->id]);
        (new SendNotificationEmail($record->id))->handle(); Mail::assertNothingSent();
        $this->user->update(['organization_id' => $record->organization_id]);
        GeneralSetting::create(['key' => 'system_email_notifications_enabled', 'value' => '0', 'user_id' => $this->user->id]);
        (new SendNotificationEmail($record->id))->handle(); Mail::assertNothingSent();
    }
    public function test_non_delivering_mailer_is_reported_as_failed(): void {
        Mail::fake(); $record = $this->notification(); config(['mail.default' => 'log']);
        (new SendNotificationEmail($record->id))->handle();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_logs', ['email_notification_id' => $record->id, 'status' => 'failed']);
    }
    public function test_existing_email_channel_sends_branded_mail_and_push_does_not_fake_success(): void {
        Mail::fake();
        $service = app(NotificationDeliveryService::class);
        \App\Models\NotificationTemplate::create(['key' => 'lead_assigned', 'channel' => 'email', 'subject' => 'Assigned: {{lead_name}}', 'body' => 'Different email body.']);
        $log = $service->send('email', $this->user->email, 'lead_assigned', ['lead_name' => 'Customer'], $this->user);
        $this->assertSame('sent', $log->status);
        Mail::assertSent(BrandedNotificationMail::class, fn($mail) => $mail->heading === 'Assigned: Customer');
        $push = $service->send('push', (string) $this->user->id, 'lead_assigned', [], $this->user);
        $this->assertSame('failed', $push->status);
        $this->assertStringContainsString('not configured', $push->last_error);
    }
    public function test_admin_can_save_one_design_and_non_admin_cannot_access_settings(): void {
        $this->user->assignRole('Super Admin');
        Livewire::actingAs($this->user)->test(EmailNotificationSettings::class)->assertSee('Browser push')
            ->set('activeDesign', 'executive')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('general_settings', ['key' => 'system_email_active_design', 'value' => 'executive']);
        Livewire::test(EmailNotificationSettings::class)->set('activeDesign', 'invalid')->call('save')->assertHasErrors('activeDesign');
        $other = User::factory()->create();
        Livewire::actingAs($other)->test(EmailNotificationSettings::class)->assertForbidden();
    }
    public function test_test_button_sends_immediately_without_a_queue_worker(): void {
        Mail::fake();
        $this->user->assignRole('Super Admin');
        \Illuminate\Support\Facades\RateLimiter::clear('email-test:'.$this->user->id);
        Livewire::actingAs($this->user)->test(EmailNotificationSettings::class)->call('sendTest')->assertHasNoErrors();
        Mail::assertSent(BrandedNotificationMail::class, fn($mail) => $mail->hasTo($this->user->email));
        Bus::assertNotDispatched(SendNotificationEmail::class);
        $this->assertDatabaseHas('notification_logs', ['channel' => 'email', 'notifiable_id' => $this->user->id, 'status' => 'sent']);
    }
    public function test_test_button_reports_transport_failure_instead_of_queued_success(): void {
        $this->user->assignRole('Super Admin');
        \Illuminate\Support\Facades\RateLimiter::clear('email-test:'.$this->user->id);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP test failure'));
        Livewire::actingAs($this->user)->test(EmailNotificationSettings::class)->call('sendTest')->assertHasErrors('emailEnabled');
        $this->assertDatabaseHas('notification_logs', ['channel' => 'email', 'status' => 'failed']);
    }
    public function test_all_designs_render_escaped_content_and_account_email_uses_shared_layout(): void {
        foreach (['classic' => 'KEEPING YOU IN THE LOOP', 'executive' => 'WORKSPACE BRIEFING', 'minimal' => 'A NOTE FROM LEAD PANTHER'] as $design => $label) {
            GeneralSetting::updateOrCreate(['key' => 'system_email_active_design'], ['value' => $design, 'user_id' => $this->user->id]);
            $html = (new BrandedNotificationMail('Title', '<script>unsafe</script>'))->render();
            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString('#111827', $html);
            $this->assertStringNotContainsString('<script>', $html);
        }
        $message = (new \Illuminate\Auth\Notifications\ResetPassword('test-token'))->toMail($this->user);
        $html = (string) app(\Illuminate\Mail\Markdown::class)->render('notifications::email', $message->data());
        $this->assertStringContainsString('Lead Panther', $html);
        $this->assertStringContainsString('#111827', $html);
        $this->assertStringContainsString('test-token', $html);
    }
    public function test_transport_failure_is_logged_and_rethrowable_for_queue_retry(): void {
        $record = $this->notification();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('transport down'));
        try { (new SendNotificationEmail($record->id))->handle(); $this->fail('Expected transport error'); }
        catch (\RuntimeException $e) { $this->assertSame('transport down', $e->getMessage()); }
        $this->assertDatabaseHas('notification_logs', ['email_notification_id' => $record->id, 'status' => 'failed', 'retries' => 1]);
    }
}
