<?php

namespace App\Traits;

use App\Models\PortalAccount;
use App\Models\WebhookLog;
use Illuminate\Http\Request;

trait LogsWebhookPayload
{
    protected function logWebhookPayload(PortalAccount $account, Request $request): WebhookLog
    {
        $payload = $request->all();

        // Redact sensitive credentials and signatures before logging to database
        $sensitiveKeys = ['authorization', 'signature', 'app_secret', 'access_token', 'secret', 'password', 'api_key'];

        foreach ($sensitiveKeys as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = '[REDACTED_SECRET]';
            }
        }

        return WebhookLog::create([
            'portal_account_id' => $account->id,
            'payload' => json_encode($payload),
            'received_at' => now(),
            'processed' => false,
            'error_message' => null,
        ]);
    }
}
