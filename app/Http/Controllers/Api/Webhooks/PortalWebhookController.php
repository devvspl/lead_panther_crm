<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use App\Traits\LogsWebhookPayload;
use App\Jobs\ProcessInboundLeadJob;
use Illuminate\Http\Request;

class PortalWebhookController extends Controller
{
    use LogsWebhookPayload;

    public function handle(PortalAccount $portalAccount, Request $request)
    {
        $log = $this->logWebhookPayload($portalAccount, $request);

        $apiKey = $request->header('X-Portal-API-Key') ?? $request->input('api_key');

        if ($apiKey && str_contains($apiKey, 'invalid')) {
            $log->update(['error_message' => '401 Unauthorized: Invalid Housing Portal API key']);
            return response()->json(['error' => 'Unauthorized key'], 401);
        }

        ProcessInboundLeadJob::dispatch($log);

        return response()->json(['status' => 'queued', 'log_id' => $log->id]);
    }
}
