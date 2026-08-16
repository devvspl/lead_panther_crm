<?php

namespace App\Services\Gateways;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function sendSms(string $toPhone, string $message, array $metadata = []): bool
    {
        Log::info("SMS Gateway Mock Send to {$toPhone}: {$message}", $metadata);
        return true;
    }
}
