<aside class="w-60 bg-surface border-r border-border min-h-screen flex flex-col justify-between p-4 flex-shrink-0">
    <div>
        <!-- Logo Top-Left -->
        <div class="flex items-center space-x-3 px-3 py-4 mb-6">
            <div class="h-9 w-9 rounded-xl bg-ink flex items-center justify-center text-white font-bold text-lg shadow-sm">
                LP
            </div>
            <span class="text-xl font-bold tracking-tight text-ink">Lead Panther</span>
        </div>

        <!-- Navigation Links Grouped by Micro-Labels -->
        <nav class="space-y-6">
            <!-- Group: MAIN -->
            <div>
                <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-muted uppercase">
                    Main
                </div>
                <div class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-pill {{ request()->routeIs('dashboard') ? 'bg-ink text-white' : 'text-muted hover:text-ink hover:bg-canvas' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>

            <!-- Group: DATABASE -->
            <div>
                <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-muted uppercase">
                    Database
                </div>
                <div class="space-y-1">
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-pill text-muted hover:text-ink hover:bg-canvas">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Leads Pipeline</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-pill text-muted hover:text-ink hover:bg-canvas">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Site Visits</span>
                    </a>
                </div>
            </div>

            <!-- Group: ANALYTICS -->
            <div>
                <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-muted uppercase">
                    Analytics
                </div>
                <div class="space-y-1">
                    <a href="{{ route('reports.index') }}" class="flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-pill {{ request()->routeIs('reports.index') ? 'bg-ink text-white' : 'text-muted hover:text-ink hover:bg-canvas' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <span>Reports & SLA</span>
                    </a>
                </div>
            </div>

            <!-- Group: SETTINGS -->
            <div>
                <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-muted uppercase">
                    Settings
                </div>
                <div class="space-y-1">
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-pill text-muted hover:text-ink hover:bg-canvas">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                        <span>Distribution Rules</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Workspace Tenant Footer -->
    <div class="border-t border-border pt-4 px-3">
        <div class="text-xs font-semibold text-ink">Tenant Scope</div>
        <div class="text-xs text-muted truncate">Client Organization #1</div>
    </div>
</aside>
