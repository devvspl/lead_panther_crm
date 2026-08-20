<x-app-layout>
    @php
        $user = auth()->user();
        $initials = strtoupper(substr($user->name ?? 'User', 0, 2));
        $roleName = $user->primary_role_name ?? ($user->getRoleNames()->first() ?: 'User');
        $orgName = $user->organization_name ?? ($user->organization?->name ?: 'Lead Panther CRM');
    @endphp

    <div class="space-y-6 w-full max-w-full">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-ink">Account & Profile Settings</h1>
                <p class="text-xs text-muted">Manage your personal credentials, contact details, security credentials,
                    and preferences.</p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center px-2.5 py-1 rounded-pill text-[11px] font-semibold bg-canvas border border-border text-ink">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                    <span>Active Account</span>
                </span>
            </div>
        </div>

        <!-- User Summary Card Banner (Full Width with Delete Action on far right) -->
        <div
            class="bg-surface rounded-card border border-border p-5 sm:p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div class="flex items-center space-x-4 min-w-0">
                <div
                    class="h-14 w-14 rounded-xl bg-ink text-white flex items-center justify-center font-bold text-lg shadow-sm ring-1 ring-border shrink-0 select-none">
                    {{ $initials }}
                </div>
                <div class="space-y-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-base font-bold text-ink truncate">{{ $user->name }}</h2>
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-canvas border border-border text-ink">
                            {{ $roleName }}
                        </span>
                    </div>
                    <p class="text-xs text-muted truncate">{{ $user->email }}</p>
                    <div class="flex items-center gap-3 text-[11px] text-muted pt-0.5 flex-wrap">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>{{ $orgName }}</span>
                        </span>
                        <span>•</span>
                        <span>Member since {{ $user->created_at ? $user->created_at->format('M Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Far Right Action & Metadata Area -->
            <div class="flex items-center gap-3 flex-wrap md:flex-nowrap shrink-0 pt-4 md:pt-0 border-t md:border-t-0 border-border">
                <div class="px-3.5 py-1.5 rounded-lg bg-canvas border border-border flex flex-col hidden sm:flex">
                    <span class="text-[10px] font-bold uppercase text-muted tracking-wider">Access Scope</span>
                    <span class="text-xs font-semibold text-ink">{{ $roleName }}</span>
                </div>
                <div class="px-3.5 py-1.5 rounded-lg bg-canvas border border-border flex flex-col hidden sm:flex">
                    <span class="text-[10px] font-bold uppercase text-muted tracking-wider">Organization</span>
                    <span class="text-xs font-semibold text-ink truncate max-w-[130px]">{{ $orgName }}</span>
                </div>
                <div class="pl-1">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>

        <!-- 2-Column Responsive Layout Grid (Profile Information & Update Password) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            <!-- Left Column: Profile Information -->
            <div class="space-y-6">
                <livewire:profile.update-profile-information-form />
            </div>

            <!-- Right Column: Update Password -->
            <div class="space-y-6">
                <livewire:profile.update-password-form />
            </div>
        </div>
    </div>
</x-app-layout>