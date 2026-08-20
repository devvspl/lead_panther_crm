@php
    $org = $organization ?? (auth()->user()?->organization ?? \App\Models\Organization::first());
    $orgName = $name ?: ($org?->name ?? 'Organization');
    $words = explode(' ', trim($orgName));
    $initials = count($words) >= 2 
        ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
        : strtoupper(substr($orgName, 0, 2));
    $orgType = ucfirst(str_replace('_', ' ', $org?->type ?? 'Builder'));
    $orgIdStr = '#ORG-' . str_pad($org?->id ?? 1, 4, '0', STR_PAD_LEFT);
@endphp

<div class="space-y-6 w-full max-w-full">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Organization Profile</h1>
            <p class="text-xs text-muted">Manage company branding, billing contact details, and organization metadata.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-1 rounded-pill text-[11px] font-semibold bg-canvas border border-border text-ink">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                <span>Active Organization</span>
            </span>
        </div>
    </div>

    <!-- Organization Summary Header Card (Full Width) -->
    <div class="bg-surface rounded-card border border-border p-5 sm:p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div class="flex items-center space-x-4 min-w-0">
            <div class="h-14 w-14 rounded-xl bg-ink text-white flex items-center justify-center font-bold text-lg shadow-sm ring-1 ring-border shrink-0 select-none">
                {{ $initials ?: 'LP' }}
            </div>
            <div class="space-y-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-base font-bold text-ink truncate">{{ $orgName }}</h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-canvas border border-border text-ink">
                        {{ $orgType }} Organization
                    </span>
                </div>
                <p class="text-xs text-muted truncate">{{ $billingEmail }}</p>
                <div class="flex items-center gap-3 text-[11px] text-muted pt-0.5 flex-wrap">
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>{{ $phone }}</span>
                    </span>
                    <span>•</span>
                    <span>{{ $org?->created_at ? 'Registered ' . $org->created_at->format('M Y') : 'Active HQ' }}</span>
                </div>
            </div>
        </div>

        <!-- Far Right Information Badges -->
        <div class="flex items-center gap-3 flex-wrap md:flex-nowrap shrink-0 pt-4 md:pt-0 border-t md:border-t-0 border-border">
            <div class="px-3.5 py-1.5 rounded-lg bg-canvas border border-border flex flex-col">
                <span class="text-[10px] font-bold uppercase text-muted tracking-wider">Organization ID</span>
                <span class="text-xs font-semibold font-mono text-ink">{{ $orgIdStr }}</span>
            </div>
            <div class="px-3.5 py-1.5 rounded-lg bg-canvas border border-border flex flex-col">
                <span class="text-[10px] font-bold uppercase text-muted tracking-wider">Access Scope</span>
                <span class="text-xs font-semibold text-ink">{{ $orgType }}</span>
            </div>
        </div>
    </div>

    <!-- Organization Information Card -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-5">
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <div class="flex items-center space-x-2">
                    <div class="w-7 h-7 rounded-lg bg-ink/10 text-ink flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider">
                        Organization Information
                    </h2>
                </div>
                <p class="text-xs text-muted pl-9">
                    Manage your organization's legal company name, billing contacts, and communication details.
                </p>
            </div>
        </div>

        <form wire:submit="save" class="space-y-4 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="org_name" class="font-bold text-ink block mb-1">Organization Legal Name <span class="text-danger">*</span></label>
                    <input 
                        wire:model="name" 
                        id="org_name" 
                        name="org_name" 
                        type="text" 
                        required 
                        placeholder="e.g. Prestige Developers Ltd"
                        class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted"
                    >
                    <x-input-error class="mt-1 text-xs text-danger" :messages="$errors->get('name')" />
                </div>

                <div>
                    <label for="billing_email" class="font-bold text-ink block mb-1">Billing Email Address <span class="text-danger">*</span></label>
                    <input 
                        wire:model="billingEmail" 
                        id="billing_email" 
                        name="billing_email" 
                        type="email" 
                        required 
                        placeholder="billing@organization.com"
                        class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted"
                    >
                    <x-input-error class="mt-1 text-xs text-danger" :messages="$errors->get('billingEmail')" />
                </div>

                <div>
                    <label for="phone" class="font-bold text-ink block mb-1">Support Contact Phone</label>
                    <input 
                        wire:model="phone" 
                        id="phone" 
                        name="phone" 
                        type="text" 
                        placeholder="+91 9876543210"
                        class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted"
                    >
                    <x-input-error class="mt-1 text-xs text-danger" :messages="$errors->get('phone')" />
                </div>

                <div>
                    <label class="font-bold text-ink block mb-1">Entity / Organization Type</label>
                    <input 
                        type="text" 
                        readonly 
                        disabled
                        value="{{ $orgType }} Organization"
                        class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas/60 text-muted text-xs font-medium cursor-not-allowed select-none"
                    >
                </div>
            </div>

            <div class="flex items-center justify-end pt-4 border-t border-border">
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition flex items-center gap-2 disabled:opacity-60 cursor-pointer shadow-xs"
                >
                    <svg wire:loading wire:target="save" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Save Organization Profile</span>
                </button>
            </div>
        </form>
    </div>
</div>