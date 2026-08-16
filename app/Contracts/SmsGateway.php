<?php

namespace App\Contracts;

interface SmsGateway
{
    public function sendSms(string $toPhone, string $message, array $metadata = []): bool;
}
