<?php

namespace App\Support;

use App\Models\User;

class NavigationConfig
{
    public static function getRoleKey(?User $user): string
    {
        if (!$user) {
            return 'guest';
        }

        $roles = $user->getRoleNames()->map(fn($r) => strtolower(str_replace(' ', '-', $r)));

        if ($roles->contains('super-admin')) return 'super-admin';
        if ($roles->contains('builder')) return 'builder';
        if ($roles->contains('channel-partner')) return 'channel-partner';
        if ($roles->contains('sales-executive')) return 'sales-executive';
        if ($roles->contains('account-manager')) return 'account-manager';
        if ($roles->contains('client')) return 'client';

        return 'guest';
    }

    public static function getAllowedItemKeys(?User $user): array
    {
        $roleKey = static::getRoleKey($user);

        $roleMap = [
            'super-admin' => [
                'dashboard', 'leads', 'bulk-import', 'distribution', 'credits',
                'replacements', 'reports', 'organizations', 'users', 'audit-logs',
                'failed-jobs', 'recharge-queue', 'backups'
            ],
            'builder' => [
                'dashboard', 'leads', 'bulk-import', 'distribution', 'reports', 'team'
            ],
            'channel-partner' => [
                'dashboard', 'leads', 'reports', 'team'
            ],
            'sales-executive' => [
                'dashboard', 'leads', 'distribution'
            ],
            'account-manager' => [
                'dashboard', 'leads', 'replacements', 'reports'
            ],
            'client' => [
                'dashboard', 'leads', 'credits', 'replacements', 'reports'
            ],
            'guest' => [],
        ];

        return $roleMap[$roleKey] ?? [];
    }

    public static function getNavItemsForUser(?User $user): array
    {
        $allowedKeys = static::getAllowedItemKeys($user);
        $roleKey = static::getRoleKey($user);

        $teamRoute = match ($roleKey) {
            'builder' => route('builder.team'),
            'channel-partner' => route('partner.team'),
            default => route('dashboard'),
        };

        $leadsRoute = match ($roleKey) {
            'account-manager' => route('account_manager.leads'),
            default => route('leads.index'),
        };

        $replacementsRoute = match ($roleKey) {
            'client' => route('replacements.client'),
            default => route('replacements.queue'),
        };

        $allMasterItems = [
            'dashboard' => [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'url' => route('dashboard'),
                'data_nav_route' => 'dashboard',
                'data_nav_exact' => 'true',
                'data_nav_root' => 'true',
                'section' => 'primary',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            'leads' => [
                'key' => 'leads',
                'label' => 'Leads',
                'url' => $leadsRoute,
                'data_nav_route' => 'leads',
                'data_nav_exact' => 'true',
                'section' => 'primary',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
            ],
            'bulk-import' => [
                'key' => 'bulk-import',
                'label' => 'Bulk Import',
                'url' => route('leads.upload'),
                'data_nav_route' => 'leads/upload',
                'data_nav_exact' => 'true',
                'section' => 'primary',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>',
            ],
            'distribution' => [
                'key' => 'distribution',
                'label' => 'Distribution',
                'url' => route('distribution.availability'),
                'data_nav_route' => 'distribution',
                'data_nav_exact' => 'false',
                'section' => 'primary',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
            ],
            'credits' => [
                'key' => 'credits',
                'label' => 'Credits & Wallet',
                'url' => route('credits.wallet'),
                'data_nav_route' => 'credits',
                'data_nav_exact' => 'true',
                'section' => 'primary',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            'replacements' => [
                'key' => 'replacements',
                'label' => 'Replacements',
                'url' => $replacementsRoute,
                'data_nav_route' => 'replacements',
                'data_nav_exact' => 'false',
                'section' => 'primary',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
            ],
            'reports' => [
                'key' => 'reports',
                'label' => 'Reports',
                'url' => route('reports.index'),
                'data_nav_route' => 'reports',
                'data_nav_exact' => 'false',
                'section' => 'primary',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
            ],
            'team' => [
                'key' => 'team',
                'label' => 'Team Management',
                'url' => $teamRoute,
                'data_nav_route' => 'team',
                'data_nav_exact' => 'false',
                'section' => 'primary',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
            ],
            'organizations' => [
                'key' => 'organizations',
                'label' => 'Organizations',
                'url' => route('admin.organizations'),
                'data_nav_route' => 'admin/organizations',
                'data_nav_exact' => 'true',
                'section' => 'database',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
            ],
            'users' => [
                'key' => 'users',
                'label' => 'Users & Roles',
                'url' => route('admin.users'),
                'data_nav_route' => 'admin/users',
                'data_nav_exact' => 'true',
                'section' => 'database',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
            ],
            'audit-logs' => [
                'key' => 'audit-logs',
                'label' => 'Audit Logs',
                'url' => route('admin.audit-logs'),
                'data_nav_route' => 'admin/audit-logs',
                'data_nav_exact' => 'true',
                'section' => 'database',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            'failed-jobs' => [
                'key' => 'failed-jobs',
                'label' => 'Failed Jobs',
                'url' => route('admin.failed-jobs'),
                'data_nav_route' => 'admin/failed-jobs',
                'data_nav_exact' => 'true',
                'section' => 'database',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ],
            'recharge-queue' => [
                'key' => 'recharge-queue',
                'label' => 'Recharge Queue',
                'url' => route('admin.recharge-requests'),
                'data_nav_route' => 'admin/recharge-requests',
                'data_nav_exact' => 'true',
                'section' => 'database',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            'backups' => [
                'key' => 'backups',
                'label' => 'Backups & Health',
                'url' => route('admin.backups'),
                'data_nav_route' => 'admin/backups',
                'data_nav_exact' => 'true',
                'section' => 'database',
                'icon' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2-2V9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>',
            ],
        ];

        $result = ['primary' => [], 'database' => []];

        foreach ($allowedKeys as $key) {
            if (isset($allMasterItems[$key])) {
                $item = $allMasterItems[$key];
                $result[$item['section']][] = $item;
            }
        }

        return $result;
    }
}
