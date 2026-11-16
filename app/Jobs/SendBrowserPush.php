<?php
namespace App\Jobs;

use App\Models\{BrowserPushSubscription, BrowserPushDelivery, SystemNotification, User};
use App\Services\BrowserPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SendBrowserPush implements ShouldQueue {
    use Queueable;
    public int $tries = 3;
    public int $timeout = 30;
    public function __construct(public string $notificationId, public int $subscriptionId) { $this->afterCommit(); }
    public function backoff(): array { return [60, 300, 900]; }
    public function handle(BrowserPushService $service): void {
        Cache::lock('browser-push:'.$this->notificationId.':'.$this->subscriptionId, 90)->block(5, function () use ($service) {
            $subscription = BrowserPushSubscription::find($this->subscriptionId);
            $notification = SystemNotification::find($this->notificationId);
            $user = $subscription ? User::find($subscription->user_id) : null;
            if (! $user || ! $notification || ! $service->configured() || ! $service->authorized($notification, $user)
                || $subscription->organization_id != $user->organization_id || $notification->read_at || $notification->created_at->lt(now()->subDay())) return;
            $delivery = BrowserPushDelivery::firstOrCreate(['notification_id' => $notification->id, 'subscription_id' => $subscription->id], ['user_id' => $user->id]);
            if (in_array($delivery->status, ['sent', 'expired'])) return;
            $delivery->increment('attempts');
            try {
                $result = $service->send($subscription, $notification);
                $delivery->update(['status' => $result['status'], 'sent_at' => $result['status'] === 'sent' ? now() : null,
                    'last_error' => $result['status'] === 'failed' ? 'Push provider did not accept this message.' : null]);
                $logs = \App\Models\NotificationLog::where('channel', 'push')->where('payload->notification_id', $notification->id);
                if ($result['status'] === 'sent') $logs->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                else $logs->where('status', '!=', 'sent')->update(['status' => 'failed', 'sent_at' => null, 'last_error' => 'Push provider did not accept this message.']);
                if ($result['status'] === 'expired') $subscription->delete();
                if ($result['retry']) throw new \RuntimeException('Temporary browser push provider failure.');
            } catch (\Throwable $e) {
                $delivery->update(['status' => 'failed', 'last_error' => 'Browser push transport failed.']);
                throw $e;
            }
        });
    }
}
