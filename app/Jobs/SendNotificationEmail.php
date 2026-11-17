<?php

namespace App\Jobs;

use App\Models\{User, NotificationLog, SystemNotification};
use App\Mail\BrandedNotificationMail;
use App\Services\{EmailDesignService, NotificationDeliveryService};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{Cache, Mail};

class SendNotificationEmail implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 60;
    public function backoff(): array { return [60, 300, 900]; }
    public function __construct(public string $notificationId) { $this->afterCommit(); }

    public function handle(): void
    {
        Cache::lock('notification-email:'.$this->notificationId, 120)->block(5, function () {
            $notification = SystemNotification::find($this->notificationId);
            if (! $notification || ! app(EmailDesignService::class)->enabled()) return;
            if (! in_array($notification->notifiable_type, [User::class, \App\Models\Client::class])) return;
            $user = User::find($notification->notifiable_id);
            if ($user && ! app(NotificationDeliveryService::class)->inbox($user)->whereKey($notification->id)->exists()) return;
            $data = $notification->data;
            if (! $user || $user->status !== 'active' || ! filter_var($user->email, FILTER_VALIDATE_EMAIL) || ($data['quarantined'] ?? false)) return;
            if (! $user->hasAnyRole(['Super Admin', 'super-admin']) && $user->organization_id != $notification->organization_id) return;
            if (! app(NotificationDeliveryService::class)->webAllowed($user, $data['category'] ?? 'delivery', (bool) ($data['important'] ?? false))) return;
            $log = NotificationLog::firstOrCreate(['email_notification_id' => $notification->id], [
                'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'channel' => 'email',
                'status' => 'pending', 'payload' => json_encode(['notification_id' => $notification->id]), 'retries' => 0,
            ]);
            if ($log->status === 'sent') return;
            if (in_array(config('mail.default'), ['log', 'array'])) {
                $log->update(['status' => 'failed', 'last_error' => 'Configure a real mail transport before sending email notifications.']);
                return;
            }
            try {
                Mail::to($user->email)->send(new BrandedNotificationMail($data['title'], $data['message'], url('/notifications/'.$notification->id.'/open')));
                $log->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed', 'retries' => $log->retries + 1, 'last_error' => 'Mail transport failed; consult protected application logs.']);
                throw $e;
            }
        });
    }
}
