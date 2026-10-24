<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Models\LeadCommunication;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class NotificationDeliveryService
{
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_CRM = 'crm';
    public const CHANNEL_PUSH = 'push';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';

    /**
     * All supported notification channels.
     */
    public static function supportedChannels(): array
    {
        return [
            self::CHANNEL_SMS => 'SMS Gateway',
            self::CHANNEL_WHATSAPP => 'WhatsApp Business',
            self::CHANNEL_EMAIL => 'Email (SMTP/SES)',
            self::CHANNEL_CRM => 'In-App CRM Notification',
            self::CHANNEL_PUSH => 'Push Notification',
        ];
    }

    /**
     * Dispatch notification across specified channel with template resolution and audit logging.
     */
    public function send(
        string $channel,
        string $recipient,
        string $templateKey,
        array $tokens = [],
        ?Model $notifiable = null,
        ?Lead $lead = null
    ): NotificationLog {
        $channel = strtolower($channel);

        // 1. Resolve Template or Default
        $template = NotificationTemplate::where('key', $templateKey)
            ->where('channel', $channel)
            ->first();

        $subject = $template?->subject ? $template->render($tokens) : "Notification: " . ucwords(str_replace('_', ' ', $templateKey));
        $body = $template ? $template->render($tokens) : $this->generateFallbackBody($templateKey, $tokens);

        // 2. Initial Pending Log
        $log = NotificationLog::create([
            'notifiable_type' => $notifiable ? get_class($notifiable) : ($lead ? Lead::class : User::class),
            'notifiable_id' => $notifiable?->getKey() ?: ($lead?->id ?: (auth()->id() ?? 1)),
            'channel' => $channel,
            'status' => self::STATUS_PENDING,
            'payload' => json_encode([
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'template_key' => $templateKey,
                'tokens' => $tokens,
            ]),
            'retries' => 0,
            'last_error' => null,
            'sent_at' => now(),
        ]);

        // 3. Dispatch Delivery via Channel Driver
        $deliveryResult = $this->dispatchToChannel($channel, $recipient, $subject, $body, $tokens);

        // 4. Update Status & Timestamps
        if ($deliveryResult['success']) {
            $log->update([
                'status' => self::STATUS_DELIVERED,
                'sent_at' => now(),
            ]);
        } else {
            $log->update([
                'status' => self::STATUS_FAILED,
                'last_error' => $deliveryResult['error'] ?? 'Delivery failure',
            ]);
        }

        // 5. If attached to a lead, track in lead_communications
        if ($lead) {
            LeadCommunication::create([
                'lead_id' => $lead->id,
                'client_id' => $lead->client_id,
                'user_id' => auth()->id() ?? 1,
                'channel' => $channel,
                'template' => $templateKey,
                'message' => $body,
                'status' => $deliveryResult['success'] ? 'delivered' : 'failed',
                'sent_at' => now(),
                'delivered_at' => $deliveryResult['success'] ? now() : null,
                'failed_at' => $deliveryResult['success'] ? null : now(),
            ]);
        }

        return $log;
    }

    /**
     * Retry a failed notification log.
     */
    public function retry(int $logId): NotificationLog
    {
        $log = NotificationLog::findOrFail($logId);
        $payload = json_decode($log->payload ?? '{}', true);

        $recipient = $payload['recipient'] ?? 'unknown';
        $subject = $payload['subject'] ?? 'Retry Notification';
        $body = $payload['body'] ?? '';
        $tokens = $payload['tokens'] ?? [];

        $deliveryResult = $this->dispatchToChannel($log->channel, $recipient, $subject, $body, $tokens);

        if ($deliveryResult['success']) {
            $log->update([
                'status' => self::STATUS_DELIVERED,
                'retries' => ($log->retries ?? 0) + 1,
                'last_error' => null,
                'sent_at' => now(),
            ]);
        } else {
            $log->update([
                'status' => self::STATUS_FAILED,
                'retries' => ($log->retries ?? 0) + 1,
                'last_error' => $deliveryResult['error'] ?? 'Retry failed',
            ]);
        }

        return $log;
    }

    /**
     * Channel transport simulation / execution.
     */
    protected function dispatchToChannel(string $channel, string $recipient, string $subject, string $body, array $tokens): array
    {
        // Fail-safe validation
        if (empty($recipient) || empty($body)) {
            return ['success' => false, 'error' => 'Missing recipient contact or body content'];
        }

        try {
            switch ($channel) {
                case self::CHANNEL_SMS:
                    // Simulated SMS Gateway Dispatch (e.g. Twilio / Gupshup / MSG91)
                    Log::info("[SMS SENT] To: {$recipient} | Msg: {$body}");
                    return ['success' => true];

                case self::CHANNEL_WHATSAPP:
                    // Simulated WhatsApp Business API Dispatch (e.g. Meta Graph API)
                    Log::info("[WHATSAPP SENT] To: {$recipient} | Msg: {$body}");
                    return ['success' => true];

                case self::CHANNEL_EMAIL:
                    // In a production setup, Mail::raw or Mailable is called. Logged here cleanly.
                    Log::info("[EMAIL SENT] To: {$recipient} | Sub: {$subject}");
                    return ['success' => true];

                case self::CHANNEL_CRM:
                    // Database in-app notification
                    return ['success' => true];

                case self::CHANNEL_PUSH:
                    // WebPush / FCM Push notification
                    Log::info("[PUSH SENT] To: {$recipient} | Sub: {$subject}");
                    return ['success' => true];

                default:
                    return ['success' => false, 'error' => "Unsupported channel [{$channel}]"];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fallback body generator if no explicit template was found.
     */
    protected function generateFallbackBody(string $key, array $tokens): string
    {
        $leadName = $tokens['lead_name'] ?? 'Lead';
        $code = $tokens['lead_code'] ?? 'LP-LEAD';
        $agent = $tokens['assigned_to'] ?? 'Agent';

        return match ($key) {
            'lead_assigned' => "Hello {$agent}, you have been assigned lead {$leadName} ({$code}). Please contact them within 15 minutes to meet SLA.",
            'lead_status_changed' => "Lead {$code} status updated to " . ($tokens['new_status'] ?? 'Updated') . ".",
            'lead_replacement_requested' => "Replacement request received for lead {$code}. Reason: " . ($tokens['reason'] ?? 'N/A') . ".",
            'credit_low_alert' => "Alert: Your credit balance is running low (" . ($tokens['balance'] ?? '0') . " credits remaining). Please recharge.",
            default => "Update for {$leadName} ({$code}): Stage is " . ($tokens['stage'] ?? 'active') . ".",
        };
    }
}
