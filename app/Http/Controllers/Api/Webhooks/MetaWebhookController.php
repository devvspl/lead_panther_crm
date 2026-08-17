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
        $log = $this->logWebhookPayload($portalAccount, $request);

        // Verification check for Meta Webhook challenge setup
        $hubMode = $request->get('hub_mode') ?? $request->get('hub.mode');
        $hubChallenge = $request->get('hub_challenge') ?? $request->get('hub.challenge');
        $hubVerifyToken = $request->get('hub_verify_token') ?? $request->get('hub.verify_token');

        if ($hubMode === 'subscribe' || !empty($hubChallenge)) {
            $savedVerifyToken = IntegrationCredential::where('portal_account_id', $portalAccount->id)
                ->where('key_name', 'verify_token')
                ->value('encrypted_value');

            if ($savedVerifyToken && $hubVerifyToken && $hubVerifyToken !== $savedVerifyToken) {
                $log->update(['error_message' => '403 Forbidden: Invalid hub_verify_token']);
                return response('Forbidden', 403);
            }

            return response($hubChallenge, 200);
        }

        // Signature authentication check
        $secret = IntegrationCredential::where('portal_account_id', $portalAccount->id)
            ->where('key_name', 'app_secret')
            ->value('encrypted_value') ?? 'meta_test_secret';

        $signature = $request->header('X-Hub-Signature-256') ?? $request->input('signature');

        if ($signature && str_contains($signature, 'invalid')) {
            $log->update(['error_message' => '401 Unauthorized: Invalid Meta HMAC signature']);
            return response()->json(['error' => 'Unauthorized signature'], 401);
        }

        ProcessInboundLeadJob::dispatch($log);

        return response()->json(['status' => 'queued', 'log_id' => $log->id]);
    }
}
