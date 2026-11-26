<?php
namespace Tests\Feature\Notifications;

use App\Jobs\SendAccountLoginDetails;
use App\Livewire\Admin\OrganizationDetail;
use App\Mail\BrandedNotificationMail;
use App\Models\{NotificationLog, NotificationTemplate, Organization, User};
use App\Services\NotificationDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Mail, Password};
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountLoginDetailsTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->org = Organization::create(['name' => 'Deltek', 'status' => 'active']);
        config(['mail.default' => 'smtp']);
        Bus::fake([SendAccountLoginDetails::class]);
        Mail::fake();
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['organization_id' => $this->org->id, 'status' => 'active'], $attributes));
    }

    private function teamComponent()
    {
        return Livewire::actingAs($this->admin)->test(OrganizationDetail::class, ['organization' => $this->org])->set('activeTab', 'team');
    }

    private function deliver(): void
    {
        foreach (NotificationLog::where('channel', 'email')->get() as $log) {
            (new SendAccountLoginDetails($log->id))->handle(app(NotificationDeliveryService::class));
        }
    }

    public function test_personalized_mail_is_private_secure_and_sent_once(): void
    {
        $a = $this->member(['name' => 'Alice']); $b = $this->member(['name' => 'Bob']);
        $component = $this->teamComponent()->set('selectedTeamUserIds', [$a->id, $a->id, $b->id])
            ->call('openAccountEmailComposer')->call('sendSelectedLoginDetails')->assertHasNoErrors()->call('sendSelectedLoginDetails');
        $this->assertSame(2, NotificationLog::count());
        Bus::assertDispatchedTimes(SendAccountLoginDetails::class, 2);
        $this->deliver(); $this->deliver();
        Mail::assertSentCount(2);
        foreach ([$a, $b] as $user) {
            Mail::assertSent(BrandedNotificationMail::class, function ($mail) use ($user) {
                if (! $mail->hasTo($user->email)) return false;
                $this->assertSame('Your Lead Panther Account Login Details', $mail->heading);
                $this->assertStringContainsString($user->name, $mail->messageBody);
                $this->assertStringContainsString('Deltek', $mail->messageBody);
                $this->assertStringContainsString($user->user_code, $mail->messageBody);
                $this->assertStringNotContainsString($user->password, $mail->messageBody);
                preg_match('~https?://[^\s]+/reset-password/([^?\s]+)~', $mail->messageBody, $matches);
                $this->assertNotEmpty($matches);
                $this->assertTrue(Password::broker()->tokenExists($user, $matches[1]));
                $this->assertEmpty($mail->cc); $this->assertEmpty($mail->bcc);
                $this->assertCount(1, $mail->to);
                return $mail->hasTo($user->email);
            });
        }
        $this->assertSame(2, NotificationLog::where('status', 'sent')->count());
        $this->assertStringNotContainsString('reset-password', NotificationLog::all()->toJson());
        $this->assertStringNotContainsString('reset-password', json_encode($component->get('loginDetailsLogIds')));
        $this->travel(61)->minutes();
        Mail::assertSent(BrandedNotificationMail::class, function ($mail) use ($a) {
            if (! $mail->hasTo($a->email)) return false;
            preg_match('~/reset-password/([^?\s]+)~', $mail->messageBody, $matches);
            $this->assertFalse(Password::broker()->tokenExists($a, $matches[1]));
            return true;
        });
    }

    public function test_foreign_users_and_unauthorized_senders_are_blocked(): void
    {
        $foreign = User::factory()->create(['organization_id' => Organization::create(['name' => 'Other'])->id]);
        $this->teamComponent()->set('selectedTeamUserIds', [$foreign->id])->call('openAccountEmailComposer')->assertForbidden();
        $member = $this->member();
        $component = $this->teamComponent()->set('selectedTeamUserIds', [$member->id]);
        $this->actingAs($member);
        $component->call('sendSelectedLoginDetails')->assertForbidden();
        $this->assertSame(0, NotificationLog::count());
    }

    public function test_select_all_respects_filters_and_changing_filters_clears_selection(): void
    {
        $a = $this->member(['name' => 'Alice']); $this->member(['name' => 'Bob']);
        $this->teamComponent()->set('userSearch', 'Alice')->call('toggleAllTeamUsers')
            ->assertSet('selectedTeamUserIds', [(string) $a->id])
            ->set('userSearch', 'Bob')->assertSet('selectedTeamUserIds', [])
            ->call('toggleAllTeamUsers')->call('toggleAllTeamUsers')->assertSet('selectedTeamUserIds', []);
    }

    public function test_failed_recipient_does_not_block_other_mail_and_stored_template_is_used(): void
    {
        $bad = $this->member(['email' => 'invalid', 'status' => 'inactive']); $good = $this->member();
        NotificationTemplate::create(['key' => 'account_login_details', 'channel' => 'email', 'subject' => 'Welcome {{user_name}}', 'body' => '{{organization_name}} {{password_setup_link}}']);
        $this->teamComponent()->set('selectedTeamUserIds', [$bad->id, $good->id])->call('openAccountEmailComposer')->call('sendSelectedLoginDetails');
        $this->deliver();
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $bad->id, 'status' => 'failed']);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $good->id, 'status' => 'sent']);
        Mail::assertSentCount(1);
        Mail::assertSent(BrandedNotificationMail::class, fn ($mail) => $mail->heading === 'Welcome '.$good->name && str_contains($mail->render(), 'Deltek'));
    }

    public function test_non_delivery_mailer_is_not_reported_as_success(): void
    {
        $member = $this->member();
        config(['mail.default' => 'log']);
        $this->teamComponent()->set('selectedTeamUserIds', [$member->id])->call('openAccountEmailComposer')->call('sendSelectedLoginDetails');
        $this->deliver();
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $member->id, 'status' => 'failed']);
        Mail::assertNothingSent();
    }
    public function test_transport_failure_is_isolated_and_failed_recipient_can_retry(): void
    {
        $a = $this->member(); $b = $this->member();
        $component = $this->teamComponent()->set('selectedTeamUserIds', [$a->id, $b->id])->call('openAccountEmailComposer')->call('sendSelectedLoginDetails');
        $mailFake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->with($a->email)->once()->andThrow(new \RuntimeException('Sensitive SMTP contents'));
        $pending = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pending->shouldReceive('send')->once();
        Mail::shouldReceive('to')->with($b->email)->once()->andReturn($pending);
        $this->deliver();
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $a->id, 'status' => 'failed']);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $b->id, 'status' => 'sent']);
        $this->assertStringNotContainsString('Sensitive SMTP contents', NotificationLog::all()->toJson());
        $this->travel(61)->seconds();
        Mail::swap($mailFake);
        $component->call('retryLoginDetails', $a->id)->assertHasNoErrors();
        $this->deliver();
        Mail::assertSentCount(1);
        $this->assertSame(2, NotificationLog::where('status', 'sent')->count());
    }

    public function test_send_button_opens_an_editable_composer_without_sending_or_creating_tokens(): void
    {
        $user = $this->member(['name' => 'Alice']);
        $component = $this->teamComponent()->set('selectedTeamUserIds', [$user->id])
            ->call('openAccountEmailComposer')->assertSet('showAccountEmailComposer', true)
            ->assertSet('accountEmailSubject', 'Your Lead Panther Account Login Details')
            ->assertSee('Compose Account Email')->assertSee('Attachments')->assertSee('Email Preview');
        $preview = $component->get('accountEmailPreview');
        $this->assertStringContainsString('Hello Alice', $preview);
        $this->assertStringNotContainsString('/reset-password/', $preview);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $component->set('showAccountEmailComposer', false);
        Bus::assertNotDispatched(SendAccountLoginDetails::class);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('notification_logs', 0);
    }

    public function test_composed_content_cc_bcc_self_copy_and_private_attachments_reach_each_recipient(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $a = $this->member(['name' => 'Alice']); $b = $this->member(['name' => 'Bob']);
        $file = \Illuminate\Http\UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf');
        $component = $this->teamComponent()->set('selectedTeamUserIds', [$a->id, $b->id])
            ->call('openAccountEmailComposer')
            ->set('accountEmailSubject', 'Welcome {{user_name}} to {{organization_name}}')
            ->set('accountEmailBody', 'Hello {{user_name}}, your UserCode is {{user_code}}. Setup: {{password_setup_link}}. Custom invoice note.')
            ->set('accountEmailCc', 'MANAGER@example.com; manager@example.com')
            ->set('accountEmailBcc', 'private@example.com, manager@example.com')
            ->set('accountEmailCopyToMe', true)->set('accountEmailAttachments', [$file])
            ->call('sendSelectedLoginDetails')->assertHasNoErrors()->assertSet('showAccountEmailComposer', false)
            ->call('sendSelectedLoginDetails')->assertHasNoErrors();
        Bus::assertDispatchedTimes(SendAccountLoginDetails::class, 2);
        $log = NotificationLog::firstOrFail();
        $metadata = json_decode($log->payload, true);
        $this->assertStringNotContainsString('manager@example.com', $log->payload);
        $draft = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($metadata['composer']), true);
        $attachment = $draft['attachments'][0];
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($attachment['path']);
        $this->assertStringStartsWith('account-email-attachments/', $attachment['path']);
        $this->deliver();
        Mail::assertSentCount(2);
        foreach ([$a, $b] as $user) {
            Mail::assertSent(BrandedNotificationMail::class, function ($mail) use ($user, $attachment) {
                if (! $mail->hasTo($user->email)) return false;
                $this->assertSame('Welcome '.$user->name.' to Deltek', $mail->heading);
                $this->assertStringContainsString('Custom invoice note.', $mail->messageBody);
                $this->assertTrue($mail->hasCc('manager@example.com'));
                $this->assertTrue($mail->hasBcc('private@example.com'));
                $this->assertTrue($mail->hasBcc($this->admin->email));
                $this->assertCount(1, $mail->to); $this->assertCount(1, $mail->cc); $this->assertCount(2, $mail->bcc);
                return $mail->hasAttachmentFromStorageDisk('local', $attachment['path'], 'invoice.pdf', ['mime' => $attachment['mime']]);
            });
        }
    }

    public function test_to_recipients_can_change_in_composer_without_using_another_organization(): void
    {
        $a = $this->member(['name' => 'Alice']); $b = $this->member(['name' => 'Bob']);
        $component = $this->teamComponent()->set('userSearch', 'Alice')->set('selectedTeamUserIds', [$a->id])
            ->call('openAccountEmailComposer')->set('selectedTeamUserIds', [$b->id])
            ->call('sendSelectedLoginDetails')->assertHasNoErrors();
        $this->deliver();
        Mail::assertSent(BrandedNotificationMail::class, fn ($mail) => $mail->hasTo($b->email));
        Mail::assertNotSent(BrandedNotificationMail::class, fn ($mail) => $mail->hasTo($a->email));
        $foreign = User::factory()->create(['organization_id' => Organization::create(['name' => 'Other org'])->id]);
        $component->set('selectedTeamUserIds', [$b->id])->call('openAccountEmailComposer')
            ->set('selectedTeamUserIds', [$foreign->id])->call('sendSelectedLoginDetails')->assertForbidden();
        $this->assertDatabaseCount('notification_logs', 1);
    }

    public function test_composer_validates_addresses_setup_token_and_attachments_before_queueing(): void
    {
        $user = $this->member();
        $component = $this->teamComponent()->set('selectedTeamUserIds', [$user->id])->call('openAccountEmailComposer')
            ->set('accountEmailBcc', 'not-an-email')->call('sendSelectedLoginDetails')->assertHasErrors('accountEmailBcc')
            ->set('accountEmailBcc', '')->set('accountEmailCc', $user->email)
            ->call('sendSelectedLoginDetails')->assertHasErrors('accountEmailCc')
            ->set('accountEmailCc', '')->set('accountEmailBody', 'Missing setup placeholder')
            ->call('sendSelectedLoginDetails')->assertHasErrors('accountEmailBody')
            ->set('accountEmailBody', 'Hello {{password_setup_link}}')
            ->set('accountEmailAttachments', [\Illuminate\Http\UploadedFile::fake()->create('too-large.pdf', 10241)])
            ->call('sendSelectedLoginDetails')->assertHasErrors('accountEmailAttachments.0')
            ->call('removeAccountEmailAttachment', 0)->assertSet('accountEmailAttachments', []);
        Bus::assertNotDispatched(SendAccountLoginDetails::class);
        $this->assertDatabaseCount('notification_logs', 0);
    }

    public function test_expired_attachments_are_pruned_and_never_silently_omitted_from_retry(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $user = $this->member();
        $this->teamComponent()->set('selectedTeamUserIds', [$user->id])->call('openAccountEmailComposer')
            ->set('accountEmailAttachments', [\Illuminate\Http\UploadedFile::fake()->create('invoice.pdf', 10)])
            ->call('sendSelectedLoginDetails')->assertHasNoErrors();
        $log = NotificationLog::firstOrFail();
        $draft = json_decode(\Illuminate\Support\Facades\Crypt::decryptString(json_decode($log->payload, true)['composer']), true);
        $path = $draft['attachments'][0]['path'];
        $this->artisan('account-emails:prune-attachments')->assertSuccessful();
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($path);
        $this->travel(8)->days();
        $this->artisan('account-emails:prune-attachments')->assertSuccessful();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
        $this->deliver();
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertStringContainsString('attachment', $log->fresh()->last_error);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Mail::assertNothingSent();
    }

}

