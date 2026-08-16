<div class="w-48 sm:w-80 relative" x-data="{ open: true }" @click.outside="open = false">
    <!-- Input Field -->
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <input 
            type="text" 
            wire:model.live.debounce.300ms="search" 
            @focus="open = true"
            placeholder="Search leads, projects, clients..." 
            class="w-full pl-9 pr-12 py-1.5 bg-canvas text-ink text-xs rounded-lg border border-border focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent focus:bg-surface placeholder:text-muted transition"
        >
        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none hidden sm:flex">
            <kbd class="px-1.5 py-0.5 text-[10px] font-semibold text-muted bg-surface border border-border rounded shadow-2xs">⌘F</kbd>
        </div>
    </div>

    <!-- Dropdown Grouped Results Overlay -->
    @if(strlen(trim($search)) >= 2)
        <div 
            x-show="open" 
            class="absolute top-full left-0 mt-2 w-80 sm:w-96 bg-surface rounded-card border border-border shadow-lg z-50 overflow-hidden text-xs max-h-96 overflow-y-auto space-y-3 p-3"
        >
            <!-- LEADS SECTION -->
            <div>
                <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-muted bg-canvas rounded">Leads</div>
                @forelse($leads as $lead)
                    <a href="{{ route('leads.index') }}" class="block px-2 py-2 hover:bg-canvas rounded transition border-b border-border/50 last:border-0">
                        <div class="flex justify-between items-center font-bold text-ink">
                            <span>{{ $lead['name'] }}</span>
                            <span class="text-[10px] font-mono text-muted">{{ $lead['lead_code'] }}</span>
                        </div>
                        <div class="text-[11px] text-muted flex justify-between">
                            <span>{{ $lead['mobile'] }}</span>
                            <span class="font-bold text-primary">{{ $lead['current_stage'] }}</span>
                        </div>
                    </a>
                @empty
                    <div class="px-2 py-1 text-[11px] text-muted italic">No leads match search query.</div>
                @endforelse
            </div>

            <!-- PROJECTS SECTION -->
            <div>
                <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-muted bg-canvas rounded">Projects</div>
                @forelse($projects as $project)
                    <a href="{{ route('dashboard') }}" class="block px-2 py-2 hover:bg-canvas rounded transition border-b border-border/50 last:border-0">
                        <div class="font-bold text-ink">{{ $project->name }}</div>
                        <div class="text-[10px] text-muted">{{ $project->city ?? 'Real Estate Project' }}</div>
                    </a>
                @empty
                    <div class="px-2 py-1 text-[11px] text-muted italic">No projects found.</div>
                @endforelse
            </div>

            <!-- CLIENTS SECTION -->
            <div>
                <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-muted bg-canvas rounded">Clients / Orgs</div>
                @forelse($clients as $client)
                    <a href="{{ route('dashboard') }}" class="block px-2 py-2 hover:bg-canvas rounded transition border-b border-border/50 last:border-0">
                        <div class="font-bold text-ink">{{ $client->name }}</div>
                    </a>
                @empty
                    <div class="px-2 py-1 text-[11px] text-muted italic">No clients found.</div>
                @endforelse
            </div>

            <!-- PEOPLE / USERS SECTION -->
            @if($people->isNotEmpty())
                <div>
                    <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-muted bg-canvas rounded">People</div>
                    @foreach($people as $person)
                        <a href="{{ route('admin.users') }}" class="block px-2 py-2 hover:bg-canvas rounded transition border-b border-border/50 last:border-0">
                            <div class="font-bold text-ink">{{ $person->name }}</div>
                            <div class="text-[10px] text-muted font-mono">{{ $person->email }}</div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
