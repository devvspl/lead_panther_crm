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
    public const ADMIN_DELIVERY_TYPES = ['delivery_failed', 'delivery_queued', 'daily_limit_reached', 'delivery_limit_reached'];

    protected function deliveredLeadsFor(User $user)
    {
        $query = Lead::withoutGlobalScopes()->where('delivery_pending', false)
            ->where('internal_processing_pending', false)->whereNotNull('delivered_at');
        $role = \App\Support\NavigationConfig::getRoleKey($user);
        if ($role === 'team-leader') return \App\Support\TeamAccess::scopeLeads($query, $user);
        if ($role === 'sales-executive') {
            return $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('followups', fn ($fq) => $fq->where('assigned_to', $user->id));
            });
        }
        return $query->where(function ($q) use ($user, $role) {
            $q->where('client_id', $user->id);
            if ($role === 'builder' && $user->organization_id) $q->orWhereHas('client', fn ($owner) => $owner->where('organization_id', $user->organization_id));
        });
    }

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
            $query->where(fn ($row) => $row->whereNull('data->type')->orWhereNotIn('data->type', self::ADMIN_DELIVERY_TYPES));
            $query->where(function ($row) use ($user) {
                $row->where(function ($unrelated) {
                    $unrelated->where(fn ($entity) => $entity->whereNull('data->entity_type')->orWhereNotIn('data->entity_type', ['lead', Lead::class]))->whereNull('data->lead_id');
                })->orWhere(function ($lead) use ($user) {
                    $lead->whereIn('data->entity_type', ['lead', Lead::class])->whereIn('data->entity_id', $this->deliveredLeadsFor($user)->select('leads.id'));
                })->orWhereIn('data->lead_id', $this->deliveredLeadsFor($user)->select('leads.id'));
            });
        }
        return $query;
    }

    public function webAllowed(User $user, string $category, bool $important): bool
    {
        $role = \App\Support\NavigationConfig::getRoleKey($user);
        return ($important && in_array($role, ['super-admin', 'builder'])) || ($user->notification_preferences[$category] ?? true);
    }

    public function sendWeb(User $recipient, string $type, string $title, string $message, string $eventKey,
        string $category, ?int $organizationId, ?string $entityType = null, ?int $entityId = null, bool $important = false, bool $sendEmail = true, bool $sendPush = true, array $context = []): ?\Illuminate\Notifications\DatabaseNotification
    {
        if (! $recipient->hasAnyRole(['Super Admin', 'super-admin']) && $recipient->organization_id != $organizationId) return null;
        if (! $recipient->hasAnyRole(['Super Admin', 'super-admin'])) {
            if (in_array($type, self::ADMIN_DELIVERY_TYPES, true)) return null;
            if (in_array($entityType, ['lead', Lead::class], true) && ! $this->deliveredLeadsFor($recipient)->whereKey($entityId)->exists()) return null;
        }
        if ($recipient->status !== 'active' || ! $this->webAllowed($recipient, $category, $important)) return null;
        $hash = hash('sha256', $recipient->id.'|'.$eventKey);
        if (!\App\Support\FinancialVisibility::allowed($recipient)) {
            $title = \App\Support\FinancialVisibility::text($title);
            $message = \App\Support\FinancialVisibility::text($message);
            $context = \App\Support\FinancialVisibility::redact($context);
        }
        $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);
        $payload = ['type' => $type, 'title' => $title, 'message' => $message, 'category' => $category,
            'organization_id' => $organizationId, 'recipient_user_id' => $recipient->id,
            'entity_type' => $entityType, 'entity_id' => $entityId,
            'action_url' => '/notifications/'.$id.'/open', 'important' => $important];
        $payload += \Illuminate\Support\Arr::only($context, ['lead_id', 'lead_code', 'failure_reason', 'failed_at']);
        // Write in the business transaction; rollbacks remove the notification too.
        // Deterministic primary/event keys protect against concurrent queue retries.
        $notification = \Illuminate\Notifications\DatabaseNotification::firstOrCreate(['id' => $id], [
            'type' => \App\Notifications\WebNotification::class, 'notifiable_type' => $recipient->getMorphClass(),
            'notifiable_id' => $recipient->id, 'organization_id' => $organizationId, 'event_key' => $hash, 'data' => $payload,
        ]);
        if ($notification->wasRecentlyCreated) {
            if ($sendPush && app(BrowserPushService::class)->configured()) {
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($notification, $recipient) {
                    try {
                        foreach (\App\Models\BrowserPushSubscription::where('user_id', $recipient->id)->where('organization_id', $recipient->organization_id)->pluck('id') as $subscriptionId) {
                            \App\Jobs\SendBrowserPush::dispatch($notification->id, $subscriptionId);
                        }
                    } catch (\Throwable $e) { report($e); }
                });
            }
            if ($sendEmail && app(EmailDesignService::class)->enabled()) {
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($notification) {
                    try { \App\Jobs\SendNotificationEmail::dispatch($notification->id); }
                    catch (\Throwable $e) { report($e); } // Email outages must not fail the business action.
                });
            }
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($recipient) {
                try { event(new \App\Events\WebNotificationsChanged($recipient->id)); }
                catch (\Throwable $e) { report($e); } // Persistence/polling survive a broadcast outage.
            });
        }
        return $notification;
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
        if ($lead->internal_processing_pending && $category === 'delivery') {
            if ($record = \App\Models\InternalLeadProcessing::where('lead_id', $lead->id)->first()) app(LeadDistributionService::class)->internalAlert($record, $type, $title, $eventKey);
            return;
        }
        $orgId = User::find($lead->client_id)?->organization_id;
        foreach ($this->organizationRecipients($orgId, $lead->client_id, $lead->assigned_to, $adminsOnly) as $recipient) {
            if (! $recipient->hasAnyRole(['Super Admin', 'super-admin']) && ($lead->delivery_pending || $lead->internal_processing_pending || ! $lead->delivered_at)) continue;
            $queued = $type !== 'delivery_failed' && $lead->delivery_pending && $category === 'delivery' && $lead->campaign_id;
            $context = ['lead_id' => $lead->id, 'lead_code' => $lead->lead_code];
            if ($type === 'delivery_failed') $context += ['failure_reason' => $lead->delivery_error, 'failed_at' => $lead->delivery_failed_at?->toIso8601String()];
            $this->sendWeb($recipient, $type, $title, $message, $eventKey, $category, $orgId, $queued ? 'campaign' : 'lead', $queued ? $lead->campaign_id : $lead->id, $important, context: $context);
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

        $subject = $template?->subject ?: "Notification: " . ucwords(str_replace('_', ' ', $templateKey));
        foreach ($tokens as $key => $value) $subject = str_replace('{{'.$key.'}}', (string) $value, $subject);
        $body = $template ? $template->render($tokens) : $this->generateFallbackBody($templateKey, $tokens);
        if (!($notifiable instanceof User && \App\Support\FinancialVisibility::allowed($notifiable))) {
            $tokens = \App\Support\FinancialVisibility::redact($tokens);
            $subject = \App\Support\FinancialVisibility::text($subject);
            $body = \App\Support\FinancialVisibility::text($body);
        }

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
            : ($channel === self::CHANNEL_PUSH ? $this->deliverPushLog($log, $recipient, $subject, $body)
                : $this->dispatchToChannel($channel, $recipient, $subject, $body, $tokens));

        // 4. Update Status & Timestamps
        if ($deliveryResult['success']) {
            $log->update([
                'status' => !empty($deliveryResult['queued']) ? self::STATUS_PENDING : ($channel === self::CHANNEL_EMAIL ? self::STATUS_SENT : self::STATUS_DELIVERED),
                'sent_at' => !empty($deliveryResult['queued']) ? null : now(),
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

    public function accountLoginEmailDefaults(): array
    {
        $template = NotificationTemplate::where('key', 'account_login_details')->where('channel', 'email')->first();
        $body = $template?->body ?: "Hello {{user_name}},\n\n{{role_introduction}}\n\n{{account_details}}\n\nLogin URL: {{login_url}}\n\nPlease use your secure password setup link to create your password and access your account:\n{{password_setup_link}}\n\nThis link expires in " . config('auth.passwords.users.expire', 60) . " minutes.\n\nRegards,\nLead Panther Team";
        if (! str_contains($body, '{{account_details}}')) $body .= "\n\n{{account_details}}";
        return ['subject' => $template?->subject ?: 'Your Lead Panther Account Login Details', 'body' => $body];
    }

    /** Resolve context from this user's organization and current assignments only. */
    public function accountLoginContext(User $user): array
    {
        $roleKey = \App\Support\NavigationConfig::getRoleKey($user);
        $roleName = match ($roleKey) {
            'builder' => 'Builder / Developer', 'team-leader' => 'Team Leader',
            'sales-executive' => 'Sales Executive', 'super-admin' => 'Super Admin',
            'client' => 'Client', 'broker' => 'Broker', 'channel-partner' => 'Channel Partner',
            default => $user->getRoleNames()->first() ?: 'Team Member',
        };
        $teams = collect();
        if ($user->organization_id && in_array($roleKey, ['builder', 'team-leader', 'sales-executive'])) {
            $teams = \App\Models\SalesTeam::where(function ($query) use ($user) {
                $query->where(fn ($q) => $q->where('ownable_type', \App\Models\Organization::class)->where('ownable_id', $user->organization_id))
                    ->orWhere(fn ($q) => $q->where('ownable_type', User::class)->whereIn('ownable_id', User::where('organization_id', $user->organization_id)->select('id')));
            })->when($roleKey === 'team-leader', function ($query) use ($user) {
                $query->where('leader_id', $user->id)->whereDoesntHave('members', fn ($q) => $q->where('user_id', $user->id)->where('is_active', false));
            })->when($roleKey === 'sales-executive', fn ($query) => $query->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true)))
                ->with(['leader' => fn ($q) => $q->where('organization_id', $user->organization_id)->where('status', 'active')])->orderBy('name')->get();
        }
        $associated = collect();
        if ($user->organization_id && in_array($roleKey, ['builder', 'team-leader', 'sales-executive'])) {
            $teams->load(['members' => fn ($query) => $query->where('is_active', true)->whereHas('user', fn ($q) => $q->where('organization_id', $user->organization_id)), 'members.user.roles']);
            if ($roleKey === 'builder') {
                // Include organization accounts that have not been assigned a team yet.
                $associated = User::where('organization_id', $user->organization_id)->with('roles')->orderBy('name')->get();
            } elseif ($roleKey === 'team-leader') {
                $associated = $teams->flatMap(fn ($team) => $team->members->pluck('user'))->filter()->unique('id')->sortBy('name');
            } else {
                $associated = $teams->pluck('leader')->filter()->unique('id');
            }
            $associated = $associated->filter(fn ($account) => $account->id !== $user->id
                && (int) $account->organization_id === (int) $user->organization_id
                && \App\Support\NavigationConfig::getRoleKey($account) !== 'super-admin');
        }
        $associatedLines = [];
        if ($associated->isNotEmpty()) {
            $associatedLines[] = match ($roleKey) {
                'builder' => 'Associated Organization Accounts',
                'team-leader' => 'Your Team Accounts',
                default => 'Your Team Leader Account',
            };
            foreach ($associated as $account) {
                $accountTeams = $teams->filter(fn ($team) => $team->leader_id === $account->id || $team->members->contains('user_id', $account->id))->pluck('name');
                $associatedLines[] = '';
                $associatedLines[] = 'Name: '.$account->name;
                $associatedLines[] = 'Role: '.($account->getRoleNames()->implode(', ') ?: 'Team Member');
                $associatedLines[] = 'UserCode: '.$account->user_code;
                $associatedLines[] = 'Email: '.$account->email;
                if (preg_match('/^\+?[0-9 ()-]{7,20}$/', (string) $account->phone)) $associatedLines[] = 'Mobile: '.$account->phone;
                $associatedLines[] = 'Account Status: '.ucfirst($account->status ?: 'Unknown');
                $associatedLines[] = 'Team: '.($accountTeams->isNotEmpty() ? $accountTeams->implode(', ') : 'Not assigned');
            }
        }
        $associatedDetails = implode("\n", $associatedLines);
        $organization = \App\Models\Organization::find($user->organization_id)?->name ?? '';
        $details = ['Organization: '.$organization, 'Account Name: '.$user->name, 'Role: '.$roleName, 'UserCode: '.$user->user_code, 'Email: '.$user->email];
        if (preg_match('/^\+?[0-9 ()-]{7,20}$/', (string) $user->phone)) $details[] = 'Mobile: '.$user->phone;
        if ($teams->isNotEmpty()) {
            $details[] = ($roleKey === 'builder' ? 'Organization Teams: ' : 'Your Team'.($teams->count() > 1 ? 's: ' : ': ')).$teams->pluck('name')->implode(', ');
            if ($roleKey === 'sales-executive') {
                foreach ($teams as $team) if ($team->leader) $details[] = 'Team Leader ('.$team->name.'): '.$team->leader->name;
            }
        }
        if ($teams->isEmpty() && in_array($roleKey, ['team-leader', 'sales-executive'])) $details[] = 'Team: Not assigned yet';
        if ($associatedDetails !== '') $details[] = "\n".$associatedDetails;
        $introduction = match ($roleKey) {
            'builder' => 'Your Builder / Developer account for '.$organization.' is ready. Your organization account details are below.',
            'team-leader' => 'Your Team Leader account is ready. Below are your personal account details and your assigned teams.',
            'sales-executive' => 'Your Sales Executive account is ready. Below are your personal account details and current team assignment.',
            default => 'Your Lead Panther account is ready. Your personal account details are below.',
        };
        return [
            'role_name' => $roleName, 'role_introduction' => $introduction, 'account_details' => implode("\n", $details),
            'organization_name' => $organization, 'team_name' => $teams->pluck('name')->implode(', '),
            'team_leader_name' => $teams->pluck('leader.name')->filter()->unique()->implode(', '),
            'associated_accounts' => $associatedDetails,
        ];
    }

    public function renderAccountLoginEmail(array $content, User $user, string $setupLink): array
    {
        $tokens = array_merge($this->accountLoginContext($user), [
            'user_name' => $user->name, 'recipient_name' => $user->name,
            'user_code' => $user->user_code, 'email' => $user->email,
            'login_url' => route('login'), 'password_setup_link' => $setupLink,
        ]);
        // One pass prevents token-looking characters in names from becoming template instructions.
        $replacements = [];
        foreach ($tokens as $key => $value) $replacements['{{'.$key.'}}'] = (string) $value;
        return ['subject' => strtr($content['subject'], $replacements), 'body' => strtr($content['body'], $replacements)];
    }

    /** Deliver one account invitation without persisting the password-reset secret. */
    public function sendAccountLoginDetails(NotificationLog $log): void
    {
        $metadata = json_decode($log->payload ?? '{}', true);
        $user = User::where('organization_id', $metadata['organization_id'] ?? null)->find($log->notifiable_id);
        $sender = User::find($metadata['sender_id'] ?? null);
        if (! $sender || $sender->status !== 'active' || ! $sender->hasAnyRole(['Super Admin', 'super-admin'])) {
            $log->update(['status' => self::STATUS_FAILED, 'last_error' => 'Sender is no longer authorized.']);
            return;
        }
        if (! $user || $user->status !== 'active' || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $log->update(['status' => self::STATUS_FAILED, 'last_error' => 'Recipient must have an active account and valid email address.']);
            return;
        }
        if (! app(EmailDesignService::class)->enabled() || in_array(config('mail.default'), ['log', 'array'])) {
            $log->update(['status' => self::STATUS_FAILED, 'last_error' => 'Enable email notifications and configure a real mail transport before retrying.']);
            return;
        }
        try {
            $content = isset($metadata['composer'])
                ? json_decode(\Illuminate\Support\Facades\Crypt::decryptString($metadata['composer']), true, flags: JSON_THROW_ON_ERROR)
                : $this->accountLoginEmailDefaults();
            foreach ($content['attachments'] ?? [] as $attachment) {
                if (! str_starts_with($attachment['path'], 'account-email-attachments/') || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($attachment['path'])) {
                    $log->update(['status' => self::STATUS_FAILED, 'last_error' => 'An attachment has expired or is unavailable. Open the composer and attach it again.']);
                    return;
                }
            }
            $result = \Illuminate\Support\Facades\Password::broker()->sendResetLink(
                ['email' => $user->email],
                function ($recipient, string $token) use ($user, $log, $content) {
                    if ($recipient->id !== $user->id) throw new \RuntimeException('Recipient mismatch');
                    $message = $this->renderAccountLoginEmail($content, $user, route('password.reset', ['token' => $token, 'email' => $user->email]));
                    $delivery = $this->dispatchToChannel('email', $user->email, $message['subject'], $message['body'], [
                        '_sensitive_account_email' => true,
                        '_account_cc' => $content['cc'] ?? [], '_account_bcc' => $content['bcc'] ?? [],
                        '_account_attachments' => $content['attachments'] ?? [],
                    ]);
                    $log->update([
                        'status' => $delivery['success'] ? self::STATUS_SENT : self::STATUS_FAILED,
                        'sent_at' => $delivery['success'] ? now() : null,
                        // Transport errors may contain message contents; keep secrets out of logs and UI.
                        'last_error' => $delivery['success'] ? null : 'Email delivery failed. Check email configuration and retry.',
                    ]);
                }
            );
            if ($result !== \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
                $log->update(['status' => self::STATUS_FAILED, 'last_error' => $result === \Illuminate\Support\Facades\Password::RESET_THROTTLED ? 'A reset link was recently requested. Wait one minute before retrying.' : 'Password setup link could not be generated.']);
            }
        } catch (\Throwable $exception) {
            $log->update(['status' => self::STATUS_FAILED, 'last_error' => 'Account email could not be sent. Please retry.']);
        }
    }

    /**
     * Retry a failed notification log.
     */
    public function retry(int $logId): NotificationLog
    {
        $log = NotificationLog::findOrFail($logId);
        if ($log->channel === self::CHANNEL_EMAIL && $log->email_notification_id) {
            if ($log->status !== self::STATUS_SENT) \App\Jobs\SendNotificationEmail::dispatch($log->email_notification_id)->afterCommit();
            return $log->fresh();
        }
        $payload = json_decode($log->payload ?? '{}', true);

        if (($payload['template_key'] ?? null) === 'account_login_details') {
            if ($log->status === self::STATUS_FAILED) {
                $log->update(['status' => self::STATUS_PENDING, 'last_error' => null]);
                \App\Jobs\SendAccountLoginDetails::dispatch($log->id);
            }
            return $log->fresh();
        }

        $recipient = $payload['recipient'] ?? 'unknown';
        $subject = $payload['subject'] ?? 'Retry Notification';
        $body = $payload['body'] ?? '';
        $tokens = $payload['tokens'] ?? [];

        $deliveryResult = $log->channel === self::CHANNEL_CRM
            ? $this->deliverCrmLog($log, $subject, $body)
            : ($log->channel === self::CHANNEL_PUSH ? $this->deliverPushLog($log, $recipient, $subject, $body)
                : $this->dispatchToChannel($log->channel, $recipient, $subject, $body, $tokens));

        if ($deliveryResult['success']) {
            $log->update([
                'status' => !empty($deliveryResult['queued']) ? self::STATUS_PENDING : ($log->channel === self::CHANNEL_EMAIL ? self::STATUS_SENT : self::STATUS_DELIVERED),
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
                    if (! app(EmailDesignService::class)->enabled()) return ['success' => false, 'error' => 'Email notifications are disabled.'];
                    if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'error' => 'Invalid email address.'];
                    if (in_array(config('mail.default'), ['log', 'array'])) return ['success' => false, 'error' => 'A real mail transport is not configured.'];
                    $mail = new \App\Mail\BrandedNotificationMail($subject, $body);
                    if (! empty($tokens['_sensitive_account_email'])) {
                        if (! empty($tokens['_account_cc'])) $mail->cc($tokens['_account_cc']);
                        if (! empty($tokens['_account_bcc'])) $mail->bcc($tokens['_account_bcc']);
                        foreach ($tokens['_account_attachments'] ?? [] as $attachment) {
                            $mail->attachFromStorageDisk('local', $attachment['path'], $attachment['name'], ['mime' => $attachment['mime']]);
                        }
                    }
                    \Illuminate\Support\Facades\Mail::to($recipient)->send($mail);
                    return ['success' => true];

                case self::CHANNEL_CRM:
                    // Database in-app notification
                    return ['success' => true];

                case self::CHANNEL_PUSH:
                    return ['success' => false, 'error' => 'Browser push requires an explicit authorized recipient.'];

                default:
                    return ['success' => false, 'error' => "Unsupported channel [{$channel}]"];
            }
        } catch (\Throwable $e) {
            if ($channel === self::CHANNEL_EMAIL && ! empty($tokens['_sensitive_account_email'])) return ['success' => false, 'error' => 'Email transport failed.'];
            if ($channel === self::CHANNEL_EMAIL) report($e);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function deliverPushLog(NotificationLog $log, string $recipient, string $title, string $message): array
    {
        if (! app(BrowserPushService::class)->configured()) return ['success' => false, 'error' => 'Browser push is not configured.'];
        $user = in_array($log->notifiable_type, [User::class, \App\Models\Client::class]) ? User::find($log->notifiable_id) : null;
        if (! $user && auth()->user()?->hasAnyRole(['Super Admin', 'super-admin'])) {
            $user = ctype_digit($recipient) ? User::find($recipient) : User::where('email', $recipient)->first();
        }
        if (! $user || ! in_array($recipient, [(string) $user->id, $user->email], true)) return ['success' => false, 'error' => 'Push requires an explicit authorized user ID or email.'];
        $subscriptions = \App\Models\BrowserPushSubscription::where('user_id', $user->id)->where('organization_id', $user->organization_id)->pluck('id');
        if ($subscriptions->isEmpty()) return ['success' => false, 'error' => 'Browser push is not configured for this recipient. Enable a browser first.'];
        $notification = $this->sendWeb($user, 'push_message', $title, $message, 'push-log:'.$log->id, 'delivery', $user->organization_id, sendEmail: false, sendPush: false);
        if (! $notification) return ['success' => false, 'error' => 'Recipient preferences or status prevented push delivery.'];
        $payload = json_decode($log->payload ?? '{}', true);
        $log->update(['payload' => json_encode(array_merge($payload, ['notification_id' => $notification->id]))]);
        \Illuminate\Support\Facades\DB::afterCommit(function () use ($notification, $subscriptions) {
            foreach ($subscriptions as $id) \App\Jobs\SendBrowserPush::dispatch($notification->id, $id);
        });
        return ['success' => true, 'queued' => true];
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
            'email_test' => "Hello " . ($tokens['recipient_name'] ?? 'there') . ",\n\nYour Lead Panther test email reached the configured mail transport. This message uses the active email design.",
            'lead_assigned' => "Hello {$agent}, you have been assigned lead {$leadName} ({$code}). Please contact them before the executive response deadline shown in Lead Panther.",
            'lead_status_changed' => "Lead {$code} status updated to " . ($tokens['new_status'] ?? 'Updated') . ".",
            'lead_replacement_requested' => "Replacement request received for lead {$code}. Reason: " . ($tokens['reason'] ?? 'N/A') . ".",
            'credit_low_alert' => "Alert: Your credit balance is running low (" . ($tokens['balance'] ?? '0') . " credits remaining). Please recharge.",
            default => "Update for {$leadName} ({$code}): Stage is " . ($tokens['stage'] ?? 'active') . ".",
        };
    }
}
