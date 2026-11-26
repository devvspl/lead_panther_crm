<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\NotificationDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SendAccountLoginDetails implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $logId)
    {
        $this->afterCommit();
    }

    public function handle(NotificationDeliveryService $delivery): void
    {
        Cache::lock('account-login-email:'.$this->logId, 120)->block(5, function () use ($delivery) {
            $log = NotificationLog::find($this->logId);
            if (! $log || $log->channel !== 'email' || $log->status !== 'pending') return;
            $metadata = json_decode($log->payload ?? '{}', true);
            if (($metadata['template_key'] ?? null) !== 'account_login_details') return;
            $delivery->sendAccountLoginDetails($log);
        });
    }
    public function failed(?\Throwable $exception): void
    {
        NotificationLog::whereKey($this->logId)->where('status', 'pending')->update([
            'status' => 'failed', 'last_error' => 'Email job did not complete. Please retry.',
        ]);
    }

}
