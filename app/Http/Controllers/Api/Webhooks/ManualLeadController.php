<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use App\Traits\LogsWebhookPayload;
use App\Jobs\ProcessInboundLeadJob;
use Illuminate\Http\Request;

class ManualLeadController extends Controller
{
    use LogsWebhookPayload;

    public function store(Request $request)
    {
        $account = PortalAccount::firstOrCreate(['name' => 'Manual CSV Import Account'], ['type' => 'owned']);

        $log = $this->logWebhookPayload($account, $request);

        ProcessInboundLeadJob::dispatchSync($log);

        return response()->json(['status' => 'success', 'message' => 'Manual lead processed successfully', 'log_id' => $log->id]);
    }
}
