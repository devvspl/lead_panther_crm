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
    public const WEB_CATEGORIES = ['assignment', 'followup', 'sla', 'replacement', 'delivery'];

    public function managedNotifications(User $user)
    {
        if ($user->hasAnyRole(['Super Admin', 'super-admin'])) {
            return \App\Models\SystemNotification::query()->whereIn('notifiable_type', [User::class, \App\Models\Client::class])->with(['recipient.roles', 'organization']);
        }
        return $this->inbox($user);
    }

    public function inbox(User $user): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        $query = $user->notifications();
        if (! $user->hasAnyRole(['Super Admin', 'super-admin'])) {
            $query->where('organization_id', $user->organization_id);
            $query->where(fn ($row) => $row->whereNull('data->quarantined')->orWhere('data->quarantined', false));
        }
        return $query;
    }

    public function webAllowed(User $user, string $category, bool $important): bool
    {
        $role = \App\Support\NavigationConfig::getRoleKey($user);
        return ($important && in_array($role, ['super-admin', 'builder'])) || ($user->notification_preferences[$category] ?? true);
    }

    public function sendWeb(User $recipient, string $type, string $title, string $message, string $eventKey,
        string $category, ?int $organizationId, ?string $entityType = null, ?int $entityId = null, bool $important = false): void
    {
        if (! $recipient->hasAnyRole(['Super Admin', 'super-admin']) && $recipient->organization_id != $organizationId) return;
        if ($recipient->status !== 'active' || ! $this->webAllowed($recipient, $category, $important)) return;
        $hash = hash('sha256', $recipient->id.'|'.$eventKey);
        $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);
        $payload = ['type' => $type, 'title' => $title, 'message' => $message, 'category' => $category,
            'organization_id' => $organizationId, 'recipient_user_id' => $recipient->id,
            'entity_type' => $entityType, 'entity_id' => $entityId,
            'action_url' => '/notifications/'.$id.'/open', 'important' => $important];
        // Write in the business transaction; rollbacks remove the notification too.
        // Deterministic primary/event keys protect against concurrent queue retries.
        $notification = \Illuminate\Notifications\DatabaseNotification::firstOrCreate(['id' => $id], [
            'type' => \App\Notifications\WebNotification::class, 'notifiable_type' => $recipient->getMorphClass(),
            'notifiable_id' => $recipient->id, 'organization_id' => $organizationId, 'event_key' => $hash, 'data' => $payload,
        ]);
        if ($notification->wasRecentlyCreated) {
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($recipient) {
                try { event(new \App\Events\WebNotificationsChanged($recipient->id)); }
                catch (\Throwable $e) { report($e); } // Persistence/polling survive a broadcast outage.
            });
        }
    }

    public function organizationRecipients(?int $organizationId, ?int $ownerId = null, ?int $executiveId = null, bool $adminsOnly = false)
    {
        return User::with('roles')->where('status', 'active')->where(function ($query) use ($organizationId, $ownerId, $executiveId) {
            $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['Super Admin', 'super-admin']));
            if ($organizationId) $query->orWhere('organization_id', $organizationId);
            if ($ownerId) $query->orWhere('id', $ownerId);
            if ($executiveId) $query->orWhere('id', $executiveId);
        })->get()->filter(function ($user) use ($organizationId, $ownerId, $executiveId, $adminsOnly) {
            if ($user->hasAnyRole(['Super Admin', 'super-admin'])) return true;
            if ($adminsOnly || $user->organization_id != $organizationId) return false;
            $role = \App\Support\NavigationConfig::getRoleKey($user);
            return $user->id === $ownerId || ($organizationId && $role === 'builder') || ($user->id === $executiveId && $role === 'sales-executive');
        });
    }

    public function leadAlert(Lead $lead, string $type, string $title, string $message, string $eventKey,
        string $category = 'delivery', bool $important = false, bool $adminsOnly = false): void
    {
        $orgId = User::find($lead->client_id)?->organization_id;
        foreach ($this->organizationRecipients($orgId, $lead->client_id, $lead->assigned_to, $adminsOnly) as $recipient) {
            if ($lead->delivery_pending && $category === 'delivery' && \App\Support\NavigationConfig::getRoleKey($recipient) === 'sales-executive') continue;
            $queued = $lead->delivery_pending && $category === 'delivery' && $lead->campaign_id;
            $this->sendWeb($recipient, $type, $title, $message, $eventKey, $category, $orgId, $queued ? 'campaign' : 'lead', $queued ? $lead->campaign_id : $lead->id, $important);
        }
    }

    public function configurationAlert(\Illuminate\Database\Eloquent\Model $record, string $type, string $title, string $message, string $eventKey): void
    {
        $project = $record instanceof \App\Models\Project ? $record : \App\Models\Project::withoutGlobalScopes()->find($record->project_id);
        if (! $project) return;
        $owner = User::find($project->client_id);
        foreach ($this->organizationRecipients($owner?->organization_id, $owner?->id) as $recipient) {
            $this->sendWeb($recipient, $type, $title, $message, $eventKey, 'delivery', $owner?->organization_id,
                $record instanceof \App\Models\Project ? 'project' : 'campaign', $record->id, true);
        }
    }

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
                'lead_id' => $lead?->id,
            ]),
            'retries' => 0,
            'last_error' => null,
            'sent_at' => now(),
        ]);

        // 3. Dispatch Delivery via Channel Driver
        $deliveryResult = $channel === self::CHANNEL_CRM
            ? $this->deliverCrmLog($log, $subject, $body)
            : $this->dispatchToChannel($channel, $recipient, $subject, $body, $tokens);

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

        $deliveryResult = $log->channel === self::CHANNEL_CRM
            ? $this->deliverCrmLog($log, $subject, $body)
            : $this->dispatchToChannel($log->channel, $recipient, $subject, $body, $tokens);

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

    private function deliverCrmLog(NotificationLog $log, string $title, string $message): array
    {
        $payload = json_decode($log->payload, true);
        $lead = $log->notifiable_type === Lead::class ? Lead::withoutGlobalScopes()->find($log->notifiable_id)
            : (! empty($payload['lead_id']) ? Lead::withoutGlobalScopes()->find($payload['lead_id']) : null);
        $recipient = in_array($log->notifiable_type, [User::class, \App\Models\Client::class]) ? User::find($log->notifiable_id) : User::find($lead?->assigned_to ?? $lead?->client_id);
        if (! $recipient) return ['success' => false, 'error' => 'CRM notification requires an explicit valid recipient.'];
        $orgId = $lead ? User::find($lead->client_id)?->organization_id : $recipient->organization_id;
        if ($lead && ! $this->organizationRecipients($orgId, $lead->client_id, $lead->assigned_to)->contains('id', $recipient->id)) {
            return ['success' => false, 'error' => 'Recipient cannot access this lead.'];
        }
        $this->sendWeb($recipient, 'crm_message', $title, $message, 'crm-log:'.$log->id, 'assignment', $orgId, $lead ? 'lead' : null, $lead?->id);
        return ['success' => true];
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
