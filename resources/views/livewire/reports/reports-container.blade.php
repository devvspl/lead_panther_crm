<div class="space-y-6">
    <!-- Main Header -->
    <div>
        <h1 class="text-xl font-bold tracking-tight text-ink">Analytics & Reporting Hub</h1>
        <p class="text-xs text-muted">Real-time SLA monitoring, source performance, replacement dispute tracking, and sales executive leaderboards.</p>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-border flex items-center space-x-2 overflow-x-auto pb-1">
        <button 
            wire:click="setTab('source')" 
            class="px-4 py-2 text-xs font-bold rounded-t-lg transition border-b-2 whitespace-nowrap {{ $activeTab === 'source' ? 'border-ink text-ink bg-surface' : 'border-transparent text-muted hover:text-ink' }}"
        >
            Source Performance
        </button>
        <button 
            wire:click="setTab('sla')" 
            class="px-4 py-2 text-xs font-bold rounded-t-lg transition border-b-2 whitespace-nowrap flex items-center space-x-1.5 {{ $activeTab === 'sla' ? 'border-ink text-ink bg-surface' : 'border-transparent text-muted hover:text-ink' }}"
        >
            <span>SLA Response</span>
            <span class="w-2 h-2 rounded-full bg-green-500 animate-ping"></span>
        </button>
        <button 
            wire:click="setTab('replacement')" 
            class="px-4 py-2 text-xs font-bold rounded-t-lg transition border-b-2 whitespace-nowrap {{ $activeTab === 'replacement' ? 'border-ink text-ink bg-surface' : 'border-transparent text-muted hover:text-ink' }}"
        >
            Replacement Rate
        </button>
        <button 
            wire:click="setTab('followup')" 
            class="px-4 py-2 text-xs font-bold rounded-t-lg transition border-b-2 whitespace-nowrap {{ $activeTab === 'followup' ? 'border-ink text-ink bg-surface' : 'border-transparent text-muted hover:text-ink' }}"
        >
            Follow-up Performance
        </button>
        <button 
            wire:click="setTab('revenue')" 
            class="px-4 py-2 text-xs font-bold rounded-t-lg transition border-b-2 whitespace-nowrap {{ $activeTab === 'revenue' ? 'border-ink text-ink bg-surface' : 'border-transparent text-muted hover:text-ink' }}"
        >
            Revenue & Bookings
        </button>
        <button 
            wire:click="setTab('executive')" 
            class="px-4 py-2 text-xs font-bold rounded-t-lg transition border-b-2 whitespace-nowrap {{ $activeTab === 'executive' ? 'border-ink text-ink bg-surface' : 'border-transparent text-muted hover:text-ink' }}"
        >
            Sales Leaderboard
        </button>
    </div>

    <!-- Tab Contents -->
    <div>
        @if($activeTab === 'source')
            <livewire:reports.source-performance />
        @elseif($activeTab === 'sla')
            <livewire:reports.sla-dashboard />
        @elseif($activeTab === 'replacement')
            <livewire:reports.replacement-rate />
        @elseif($activeTab === 'followup')
            <livewire:reports.followup-performance />
        @elseif($activeTab === 'revenue')
            <livewire:reports.revenue-and-bookings />
        @elseif($activeTab === 'executive')
            <livewire:reports.sales-executive-performance />
        @endif
    </div>
</div>
