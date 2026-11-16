<?php
namespace App\Http\Controllers;

use App\Models\{BrowserPushSubscription, SystemNotification, AuditLog, User};
use App\Services\{BrowserPushService, NotificationDeliveryService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrowserPushController extends Controller {
    private function guard(Request $request): void { abort_unless($request->user()?->status === 'active', 403); }
    public function config(Request $request, BrowserPushService $service) {
        $this->guard($request);
        return response()->json(['configured' => $service->configured(), 'publicKey' => $service->keys()['publicKey'] ?? null,
            'userId' => $request->user()->id, 'workerUrl' => asset('browser-push-sw.js')])->header('Cache-Control', 'no-store');
    }
    public function status(Request $request) {
        $this->guard($request);
        $request->validate(['endpoint' => 'required|string|max:4096']);
        return response()->json(['subscribed' => BrowserPushSubscription::where('user_id', $request->user()->id)
            ->where('organization_id', $request->user()->organization_id)->where('endpoint_hash', hash('sha256', $request->endpoint))->exists()]);
    }
    public function store(Request $request, BrowserPushService $service) {
        $this->guard($request);
        abort_unless($service->configured(), 503, 'Browser push keys have not been configured.');
        $data = $request->validate(['endpoint' => 'required|string|max:4096', 'keys.p256dh' => 'required|string|max:128', 'keys.auth' => 'required|string|max:64']);
        abort_unless($service->validEndpoint($data['endpoint']), 422, 'Unsupported browser push endpoint.');
        foreach (['p256dh' => 65, 'auth' => 16] as $key => $bytes) {
            $encoded = $data['keys'][$key];
            $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
            abort_unless(preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $encoded) && $decoded !== false && strlen($decoded) === $bytes && ($key !== 'p256dh' || ord($decoded[0]) === 4), 422, 'Invalid subscription encryption keys.');
        }
        $record = DB::transaction(function () use ($request, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', $data['endpoint']);
            $existing = BrowserPushSubscription::where('endpoint_hash', $hash)->lockForUpdate()->first();
            abort_if($existing && $existing->user_id !== $request->user()->id, 409, 'Please renew this browser subscription.');
            abort_if(! $existing && BrowserPushSubscription::where('user_id', $request->user()->id)->count() >= 20, 422, 'Maximum browser subscriptions reached.');
            return BrowserPushSubscription::updateOrCreate(['endpoint_hash' => $hash], ['user_id' => $request->user()->id,
                'organization_id' => $request->user()->organization_id, 'subscription' => ['endpoint' => $data['endpoint'], 'keys' => $data['keys'], 'contentEncoding' => 'aes128gcm']]);
        });
        $request->session()->put('browser_push_endpoint_hash', $record->endpoint_hash);
        $this->audit('browser_push.enabled', $record->id);
        return response()->json(['subscribed' => true]);
    }
    public function destroy(Request $request) {
        $this->guard($request);
        $request->validate(['endpoint' => 'required|string|max:4096']);
        $record = BrowserPushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $request->endpoint))->firstOrFail();
        $this->audit('browser_push.disabled', $record->id);
        $record->delete();
        $request->session()->forget('browser_push_endpoint_hash');
        return response()->json(['subscribed' => false]);
    }
    public function test(Request $request, BrowserPushService $service) {
        $this->guard($request);
        abort_unless($service->configured(), 503, 'Browser push is not configured.');
        abort_unless(app(NotificationDeliveryService::class)->webAllowed($request->user(), 'delivery', false), 422, 'Enable delivery alerts in your notification preferences before testing.');
        $request->validate(['endpoint' => 'required|string|max:4096']);
        $subscription = BrowserPushSubscription::where('user_id', $request->user()->id)->where('organization_id', $request->user()->organization_id)
            ->where('endpoint_hash', hash('sha256', $request->endpoint))->firstOrFail();
        $user = $request->user();
        $notification = app(NotificationDeliveryService::class)->sendWeb($user, 'browser_push_test', 'Browser push connected',
            'This browser can receive notifications from your Lead Panther workspace.', 'push-test:'.\Illuminate\Support\Str::uuid(), 'delivery', $user->organization_id, sendEmail: false, sendPush: false);
        abort_unless($notification, 422, 'Notification preferences prevented the test.');
        try { (new \App\Jobs\SendBrowserPush($notification->id, $subscription->id))->handle($service); }
        catch (\Throwable $e) { report($e); abort(502, 'Push test failed. Check server transport logs.'); }
        $delivery = \App\Models\BrowserPushDelivery::where('notification_id', $notification->id)->where('subscription_id', $subscription->id)->first();
        abort_unless($delivery?->status === 'sent', 502, 'Push was not accepted. Re-enable this browser subscription and retry.');
        return response()->json(['message' => 'Test accepted by the browser push provider. Check your device notifications.']);
    }
    public function message(Request $request, string $id, BrowserPushService $service) {
        $this->guard($request);
        $notification = SystemNotification::findOrFail($id);
        abort_unless($service->authorized($notification, $request->user()) && ! $notification->read_at, 404);
        return response()->json(['title' => $notification->data['title'], 'body' => $notification->data['message'],
            'url' => url('/notifications/'.$notification->id.'/open'), 'tag' => 'lead-panther-'.$notification->id])->header('Cache-Control', 'no-store');
    }
    private function audit(string $action, int $id): void {
        AuditLog::create(['user_id' => auth()->id(), 'organization_id' => auth()->user()->organization_id,
            'action' => $action, 'subject_type' => BrowserPushSubscription::class, 'subject_id' => $id,
            'context' => ['category' => 'notification', 'status' => 'success'], 'created_at' => now()]);
    }
}
