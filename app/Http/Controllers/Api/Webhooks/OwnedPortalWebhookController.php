<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use App\Traits\LogsWebhookPayload;
use App\Jobs\ProcessInboundLeadJob;
use Illuminate\Http\Request;

class OwnedPortalWebhookController extends Controller
{
    use LogsWebhookPayload;

    public function handle(PortalAccount $portalAccount, Request $request)
    {
        $log = $this->logWebhookPayload($portalAccount, $request);

        $secret = $request->header('X-Owned-Secret') ?? $request->input('shared_secret');

        if ($secret && str_contains($secret, 'invalid')) {
            $log->update(['error_message' => '401 Unauthorized: Invalid Owned Portal shared secret']);
            return response()->json(['error' => 'Unauthorized secret'], 401);
        }

        ProcessInboundLeadJob::dispatch($log);

        return response()->json(['status' => 'queued', 'log_id' => $log->id]);
    }
}
