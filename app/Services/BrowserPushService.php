<?php
namespace App\Services;

use App\Models\{BrowserPushSubscription, SystemNotification, User};
use Illuminate\Support\Facades\{Crypt, Storage};
use Minishlink\WebPush\{WebPush, Subscription};

class BrowserPushService
{
    public function prepareCrypto(): void {
        $path = config('webpush.openssl_conf');
        if (! $path && PHP_OS_FAMILY === 'Windows' && ! getenv('OPENSSL_CONF')) {
            $candidate = dirname(php_ini_loaded_file() ?: PHP_BINARY).'/extras/ssl/openssl.cnf';
            if (is_file($candidate)) $path = $candidate;
        }
        if ($path && is_file($path) && function_exists('putenv')) putenv('OPENSSL_CONF='.$path);
    }
    public function keys(): ?array {
        if (! config('webpush.enabled')) return null;
        if (config('webpush.public_key') && config('webpush.private_key')) return [
            'publicKey' => config('webpush.public_key'), 'privateKey' => config('webpush.private_key'),
            'subject' => config('webpush.subject') ?: 'mailto:'.config('mail.from.address'),
        ];
        try {
            if (Storage::disk('local')->exists('webpush-vapid.json')) return json_decode(Crypt::decryptString(Storage::disk('local')->get('webpush-vapid.json')), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) { report($e); }
        return null;
    }
    public function configured(): bool { return (bool) $this->keys(); }

    public function validEndpoint(string $endpoint): bool {
        $url = parse_url($endpoint);
        if (! $url || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || (isset($url['port']) && $url['port'] !== 443)) return false;
        $host = strtolower($url['host'] ?? '');
        return in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'])
            || str_ends_with($host, '.push.services.mozilla.com') || str_ends_with($host, '.notify.windows.com') || str_ends_with($host, '.wns.windows.com');
    }

    public function authorized(SystemNotification $notification, User $user): bool {
        return in_array($notification->notifiable_type, [User::class, \App\Models\Client::class])
            && (int) $notification->notifiable_id === $user->id && $user->status === 'active'
            && ($user->hasAnyRole(['Super Admin', 'super-admin']) || $user->organization_id == $notification->organization_id)
            && ! ($notification->data['quarantined'] ?? false)
            && app(NotificationDeliveryService::class)->webAllowed($user, $notification->data['category'] ?? 'delivery', (bool) ($notification->data['important'] ?? false));
    }

    public function send(BrowserPushSubscription $record, SystemNotification $notification): array {
        $this->prepareCrypto();
        $keys = $this->keys();
        if (! $keys || ! $this->validEndpoint($record->subscription['endpoint'] ?? '')) return ['status' => 'failed', 'retry' => false];
        // Never follow redirects from push endpoints or use application/proxy credentials.
        $push = new WebPush(['VAPID' => $keys], ['TTL' => 900], $this->httpClient());
        $report = $push->sendOneNotification(Subscription::create($record->subscription), json_encode([
            'notificationId' => $notification->id,
            'messageUrl' => route('notifications.push.message', $notification->id),
        ], JSON_THROW_ON_ERROR));
        if ($report->isSuccess()) return ['status' => 'sent', 'retry' => false];
        if ($report->isSubscriptionExpired()) return ['status' => 'expired', 'retry' => false];
        $code = $report->getResponse()?->getStatusCode();
        return ['status' => 'failed', 'retry' => ! $code || $code === 429 || $code >= 500];
    }

    protected function httpClient(): \Psr\Http\Client\ClientInterface {
        return new \GuzzleHttp\Client(['timeout' => 15, 'connect_timeout' => 5, 'allow_redirects' => false]);
    }
}
