<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\User;

class LeadPresenter
{
    public static function isAccountManager(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) return false;

        return $user->hasRole('Account Manager') || $user->hasRole('account-manager');
    }

    public static function maskMobile(string $mobile): string
    {
        $digits = preg_replace('/[^0-9]/', '', $mobile);
        $len = strlen($digits);

        if ($len < 4) {
            return str_repeat('X', $len);
        }

        $prefix = substr($digits, 0, 2);
        $suffix = substr($digits, -2);
        $maskedLen = max(0, $len - 4);

        return $prefix . str_repeat('X', $maskedLen) . $suffix;
    }

    public static function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '***@***.com';
        }

        [$name, $domain] = explode('@', $email, 2);
        $len = strlen($name);

        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', $len - 2) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    public static function present(Lead $lead, ?User $user = null): array
    {
        $shouldMask = static::isAccountManager($user);

        return [
            'id' => $lead->id,
            'lead_code' => $lead->lead_code,
            'name' => $lead->name,
            'mobile' => $shouldMask ? static::maskMobile($lead->mobile ?? '') : $lead->mobile,
            'email' => $shouldMask ? static::maskEmail($lead->email ?? '') : $lead->email,
            'city' => $lead->city,
            'budget' => $lead->budget,
            'property_type' => $lead->property_type,
            'requirement' => $lead->requirement,
            'current_stage' => $lead->current_stage,
            'status' => $lead->status,
            'project_name' => $lead->project?->name ?? 'N/A',
            'client_name' => $lead->client?->name ?? 'N/A',
            'campaign_name' => $lead->campaign?->name ?? 'Direct',
            'source_name' => $lead->leadSource?->name ?? 'Inbound',
            'assigned_executive' => $lead->assignedTo?->name ?? 'Unassigned',
            'created_at' => $lead->created_at?->format('M d, Y H:i'),
            'is_masked' => $shouldMask,
        ];
    }
}
