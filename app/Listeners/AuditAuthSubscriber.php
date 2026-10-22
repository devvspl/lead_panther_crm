<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Throwable;

class AuditAuthSubscriber
{
    public function handleLogin(Login $event): void
    {
        try {
            AuditLog::create([
                'user_id' => $event->user?->id,
                'action' => 'auth.login',
                'subject_type' => $event->user ? get_class($event->user) : null,
                'subject_id' => $event->user?->id,
                'from_value' => null,
                'to_value' => json_encode([
                    'status' => 'success',
                    'email' => $event->user?->email,
                    'name' => $event->user?->name,
                    'guard' => $event->guard,
                ]),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Fail open for audit logging
        }
    }

    public function handleLogout(Logout $event): void
    {
        try {
            AuditLog::create([
                'user_id' => $event->user?->id,
                'action' => 'auth.logout',
                'subject_type' => $event->user ? get_class($event->user) : null,
                'subject_id' => $event->user?->id,
                'from_value' => json_encode([
                    'email' => $event->user?->email,
                    'name' => $event->user?->name,
                ]),
                'to_value' => json_encode(['status' => 'logged_out']),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Fail open
        }
    }

    public function handleFailed(Failed $event): void
    {
        try {
            $credentials = $event->credentials ?? [];
            unset($credentials['password'], $credentials['password_confirmation'], $credentials['token']);

            AuditLog::create([
                'user_id' => $event->user?->id,
                'action' => 'auth.failed',
                'subject_type' => $event->user ? get_class($event->user) : null,
                'subject_id' => $event->user?->id,
                'from_value' => json_encode(['credentials' => $credentials]),
                'to_value' => json_encode(['status' => 'failed_attempt']),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Fail open
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        try {
            AuditLog::create([
                'user_id' => $event->user?->id,
                'action' => 'auth.password_reset',
                'subject_type' => $event->user ? get_class($event->user) : null,
                'subject_id' => $event->user?->id,
                'from_value' => null,
                'to_value' => json_encode(['status' => 'password_reset_success', 'email' => $event->user?->email]),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Fail open
        }
    }

    public function handleLockout(Lockout $event): void
    {
        try {
            AuditLog::create([
                'user_id' => null,
                'action' => 'auth.lockout',
                'subject_type' => null,
                'subject_id' => null,
                'from_value' => json_encode(['request_ip' => $event->request->ip()]),
                'to_value' => json_encode(['status' => 'throttled_lockout']),
                'ip_address' => $event->request->ip(),
                'user_agent' => $event->request->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Fail open
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            PasswordReset::class => 'handlePasswordReset',
            Lockout::class => 'handleLockout',
        ];
    }
}
