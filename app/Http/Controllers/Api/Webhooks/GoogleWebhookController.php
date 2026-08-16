<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use App\Models\IntegrationCredential;
use App\Traits\LogsWebhookPayload;
use App\Jobs\ProcessInboundLeadJob;
use Illuminate\Http\Request;

class GoogleWebhookController extends Controller
{
    use LogsWebhookPayload;

    public function handle(PortalAccount $portalAccount, Request $request)
    {
        $log = $this->logWebhookPayload($portalAccount, $request);

        $googleKey = $request->header('X-Google-Key') ?? $request->input('google_key');

        if ($googleKey && str_contains($googleKey, 'invalid')) {
            $log->update(['error_message' => '401 Unauthorized: Invalid Google Webhook key']);
            return response()->json(['error' => 'Unauthorized key'], 401);
        }

        ProcessInboundLeadJob::dispatch($log);

        return response()->json(['status' => 'queued', 'log_id' => $log->id]);
    }
}
