<?php

namespace App\Contracts;

interface WhatsAppGateway
{
    public function sendMessage(string $toPhone, string $message, array $metadata = []): bool;
}
