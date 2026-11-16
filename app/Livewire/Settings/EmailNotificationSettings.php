<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\GeneralSetting;
use App\Services\{EmailDesignService, NotificationDeliveryService};
use Illuminate\Support\Facades\{DB, RateLimiter, Cache};
use Illuminate\Validation\Rule;

class EmailNotificationSettings extends Component
{
    public bool $emailEnabled = true;
    public string $activeDesign = 'classic';

    private function guard(): void { abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403); }

    public function mount(): void
    {
        $this->guard();
        $service = app(EmailDesignService::class);
        $this->emailEnabled = $service->enabled();
        $this->activeDesign = $service->active();
    }

    public function save(): void
    {
        $this->guard();
        $this->validate(['activeDesign' => ['required', Rule::in(array_keys(EmailDesignService::DESIGNS))], 'emailEnabled' => 'boolean']);
        Cache::lock('system-email-settings', 15)->block(5, fn () => DB::transaction(function () {
            foreach (['system_email_notifications_enabled' => $this->emailEnabled ? '1' : '0', 'system_email_active_design' => $this->activeDesign] as $key => $value) {
                GeneralSetting::updateOrCreate(['key' => $key], ['value' => $value, 'user_id' => auth()->id()]);
            }
        }));
        $this->dispatch('toast', type: 'success', message: 'System email settings saved. The active design applies to outgoing emails.');
    }

    public function sendTest(): void
    {
        $this->guard();
        if (! app(EmailDesignService::class)->enabled() || in_array(config('mail.default'), ['log', 'array'])) {
            $this->addError('emailEnabled', 'Enable email and configure a real mail transport before sending a test.');
            return;
        }
        if (RateLimiter::tooManyAttempts('email-test:'.auth()->id(), 1)) {
            $this->addError('emailEnabled', 'Please wait one minute before sending another test.');
            return;
        }
        RateLimiter::hit('email-test:'.auth()->id(), 60);
        $user = auth()->user();
        $this->resetErrorBag('emailEnabled');
        try {
            // A connection test must exercise SMTP now, even when no queue worker is running.
            $log = app(NotificationDeliveryService::class)->send('email', $user->email, 'email_test', ['recipient_name' => $user->name], $user);
            if ($log->status !== NotificationDeliveryService::STATUS_SENT) {
                $this->addError('emailEnabled', 'Test email failed. Check SMTP host, port, encryption, credentials and sender address. See application logs for the transport error.');
                $this->dispatch('toast', type: 'error', message: 'Test email failed; it was not accepted by the mail transport.');
                return;
            }
            $this->dispatch('toast', type: 'success', message: 'Test email accepted by the mail transport for '.$user->email.'. Check your inbox and spam folder.');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('emailEnabled', 'Test email could not be completed. Check application logs and database migrations.');
        }
    }

    public function render()
    {
        $this->guard();
        return view('livewire.settings.email-notification-settings', [
            'designs' => EmailDesignService::DESIGNS,
            'mailer' => config('mail.default'),
            'broadcastEnabled' => ! in_array(config('broadcasting.default'), [null, 'null', 'log']),
            'queueDriver' => config('queue.default'),
            'pendingEmailJobs' => config('queue.default') === 'database'
                ? DB::table(config('queue.connections.database.table', 'jobs'))->where('payload', 'like', '%SendNotificationEmail%')->count() : null,
            'deliveryLogs' => \App\Models\NotificationLog::where('channel', 'email')->latest('id')->take(10)->get(),
        ]);
    }
}
