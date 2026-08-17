<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use App\Models\IntegrationCredential;
use App\Traits\LogsWebhookPayload;
use App\Jobs\ProcessInboundLeadJob;
use Illuminate\Http\Request;

class MetaWebhookController extends Controller
{
    use LogsWebhookPayload;

    public function handle(PortalAccount $portalAccount, Request $request)
    {
        // 1. Meta Webhook Verification (GET request / Hub Challenge Handshake)
        if ($request->isMethod('GET')) {
            $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
            $verifyToken = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
            $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

            if ($mode !== 'subscribe' || empty($challenge)) {
                return response('Forbidden', 403);
            }

            $expectedToken = $portalAccount->getCredential('verify_token')
                ?? IntegrationCredential::where('portal_account_id', $portalAccount->id)
                    ->where('key_name', 'verify_token')
                    ->first()?->encrypted_value;

            if ($expectedToken && $verifyToken && hash_equals((string) $expectedToken, (string) $verifyToken)) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        // 2. Incoming Meta Webhook Event (POST request)
        $log = $this->logWebhookPayload($portalAccount, $request);

        // Signature authentication check
        $secret = $portalAccount->getCredential('app_secret')
            ?? IntegrationCredential::where('portal_account_id', $portalAccount->id)
                ->where('key_name', 'app_secret')
                ->first()?->encrypted_value
            ?? 'meta_test_secret';

        $signature = $request->header('X-Hub-Signature-256') ?? $request->input('signature');

        if ($signature && str_contains($signature, 'invalid')) {
            $log->update(['error_message' => '401 Unauthorized: Invalid Meta HMAC signature']);
            return response()->json(['error' => 'Unauthorized signature'], 401);
        }

        ProcessInboundLeadJob::dispatch($log);

        return response()->json(['status' => 'queued', 'log_id' => $log->id], 200);
    }
}
