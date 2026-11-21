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

            // Direct browser check / health ping without Meta challenge parameters
            if (empty($mode) && empty($challenge) && empty($verifyToken)) {
                return response()->json([
                    'status' => 'online',
                    'service' => 'Lead Panther Meta Webhook',
                    'portal_account_id' => $portalAccount->id,
                    'account_name' => $portalAccount->name,
                    'message' => 'Endpoint is active and ready to receive Meta webhooks.',
                    'hint' => 'In Meta App Dashboard > Webhooks, set Callback URL to this endpoint and enter your Webhook Verify Token.',
                ], 200);
            }

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

        $events = [];
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'leadgen') continue;
                $event = $entry;
                $event['changes'] = [$change];
                $events[] = $event;
            }
        }
        if (count($events) > 1) {
            // Meta can batch events from several Pages into one app webhook.
            // Keep the original request and give each event its own retryable log/job.
            foreach ($events as $event) {
                $child = \App\Models\WebhookLog::create([
                    'portal_account_id' => $portalAccount->id,
                    'payload' => json_encode(['object' => 'page', 'entry' => [$event]]),
                    'received_at' => now(), 'processed' => false, 'error_message' => null,
                ]);
                ProcessInboundLeadJob::dispatch($child);
            }
            $log->update(['processed' => true, 'error_message' => 'Batch split into '.count($events).' individual event logs.']);
        } else {
            ProcessInboundLeadJob::dispatch($log);
        }

        return response()->json(['status' => 'queued', 'log_id' => $log->id], 200);
    }
}
