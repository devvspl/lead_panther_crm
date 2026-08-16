<?php

namespace App\Services\Gateways;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

class LogWhatsAppGateway implements WhatsAppGateway
{
    public function sendMessage(string $toPhone, string $message, array $metadata = []): bool
    {
        Log::info("WhatsApp Gateway Mock Send to {$toPhone}: {$message}", $metadata);
        return true;
    }
}
