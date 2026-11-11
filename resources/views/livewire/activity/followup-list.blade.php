<div class="space-y-6">

    @if(!$lead)
        <!-- Standalone Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-border">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-ink tracking-tight">My Follow-ups</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        {{ $myToday->count() + $myOverdue->count() }} Due
                    </span>
                </div>
                <p class="text-xs text-muted mt-0.5">Track, complete, and reschedule your scheduled calls and tasks</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('leads.index') }}" wire:navigate class="px-3 py-1.5 rounded-lg border border-border bg-surface text-xs font-semibold text-ink hover:bg-canvas transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Go to My Leads</span>
                </a>
            </div>
        </div>
    @endif

    <!-- Sales Executive Widget: My Follow-ups Today -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">My Follow-ups Roster</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Overdue -->
            <div class="p-4 rounded-card border border-red-200 bg-red-50/50 space-y-2">
                <span class="text-xs font-bold text-red-700">Overdue ({{ $myOverdue->count() }})</span>
                @forelse($myOverdue as $f)
                    <div class="p-2.5 bg-surface rounded border border-red-200 text-xs flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('leads.index') }}?search={{ $f->lead?->lead_code ?: $f->lead_id }}" wire:navigate class="font-bold text-ink hover:text-primary hover:underline block truncate">
                                {{ $f->lead?->name ?? 'Lead #'.$f->lead_id }}
                            </a>
                            <p class="text-[10px] text-muted truncate">{{ $f->lead?->project?->name ? $f->lead->project->name.' • ' : '' }}{{ $f->note ?: 'Scheduled follow-up' }}</p>
                            <p class="text-[10px] font-mono text-red-600 font-semibold mt-0.5">{{ \Carbon\Carbon::parse($f->due_at)->format('M d, H:i') }}</p>
                        </div>
                        <button wire:click="markDone({{ $f->id }})" class="text-[10px] bg-red-600 hover:bg-red-700 text-white font-bold px-2 py-1 rounded shrink-0 cursor-pointer">Mark Done</button>
                    </div>
                @empty
                    <p class="text-[11px] text-muted">No overdue follow-ups.</p>
                @endforelse
            </div>

            <!-- Today -->
            <div class="p-4 rounded-card border border-amber-200 bg-amber-50/50 space-y-2">
                <span class="text-xs font-bold text-amber-700">Due Today ({{ $myToday->count() }})</span>
                @forelse($myToday as $f)
                    <div class="p-2.5 bg-surface rounded border border-amber-200 text-xs flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('leads.index') }}?search={{ $f->lead?->lead_code ?: $f->lead_id }}" wire:navigate class="font-bold text-ink hover:text-primary hover:underline block truncate">
                                {{ $f->lead?->name ?? 'Lead #'.$f->lead_id }}
                            </a>
                            <p class="text-[10px] text-muted truncate">{{ $f->lead?->project?->name ? $f->lead->project->name.' • ' : '' }}{{ $f->note ?: 'Scheduled follow-up' }}</p>
                            <p class="text-[10px] font-mono text-amber-700 font-semibold mt-0.5">{{ \Carbon\Carbon::parse($f->due_at)->format('H:i') }}</p>
                        </div>
                        <button wire:click="markDone({{ $f->id }})" class="text-[10px] bg-amber-600 hover:bg-amber-700 text-white font-bold px-2 py-1 rounded shrink-0 cursor-pointer">Mark Done</button>
                    </div>
                @empty
                    <p class="text-[11px] text-muted">No follow-ups due today.</p>
                @endforelse
            </div>

            <!-- Upcoming -->
            <div class="p-4 rounded-card border border-border bg-canvas space-y-2">
                <span class="text-xs font-bold text-ink">Upcoming ({{ $myUpcoming->count() }})</span>
                @forelse($myUpcoming as $f)
                    <div class="p-2.5 bg-surface rounded border border-border text-xs flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('leads.index') }}?search={{ $f->lead?->lead_code ?: $f->lead_id }}" wire:navigate class="font-bold text-ink hover:text-primary hover:underline block truncate">
                                {{ $f->lead?->name ?? 'Lead #'.$f->lead_id }}
                            </a>
                            <p class="text-[10px] text-muted truncate">{{ $f->lead?->project?->name ? $f->lead->project->name.' • ' : '' }}{{ $f->note ?: 'Scheduled follow-up' }}</p>
                            <p class="text-[10px] font-mono text-muted mt-0.5">{{ \Carbon\Carbon::parse($f->due_at)->format('M d, H:i') }}</p>
                        </div>
                        <button wire:click="markDone({{ $f->id }})" class="text-[10px] bg-ink hover:bg-neutral-800 text-white font-bold px-2 py-1 rounded shrink-0 cursor-pointer">Mark Done</button>
                    </div>
                @empty
                    <p class="text-[11px] text-muted">No upcoming follow-ups.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Complete Next-Step Modal -->
    @if($completingFollowupId)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4">
                <h3 class="text-sm font-bold text-ink">Complete Follow-up: Schedule Next Action</h3>
                <p class="text-xs text-muted">A follow-up cannot be closed without either a next follow-up date or explicit stage update.</p>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-ink">Next Follow-up Date & Time</label>
                        <input type="datetime-local" wire:model="nextDueAt" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                    </div>

                    <div>
                        <label class="font-bold text-ink">Next Action Notes</label>
                        <textarea wire:model="nextNote" rows="2" placeholder="e.g. Call back after client reviews site visit brochure" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Optional Stage Change</label>
                        <x-ui.themed-select 
                            wire:model="newStage"
                            :options="['' => 'Keep Current Stage', 'connected' => 'Connected', 'qualified' => 'Qualified', 'meeting' => 'Meeting Scheduled', 'site_visit' => 'Site Visit', 'negotiation' => 'Negotiation']"
                            placeholder="Keep Current Stage"
                            searchable="true"
                            class="w-full mt-1"
                        />
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button wire:click="$set('completingFollowupId', null)" class="text-xs text-muted hover:underline px-3 py-2">Cancel</button>
                    <x-ui.button wire:click="completeWithNextStep" variant="primary" class="text-xs">
                        Save Next Step & Complete
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
