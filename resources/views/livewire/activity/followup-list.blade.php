<div class="space-y-6">

    <!-- Sales Executive Widget: My Follow-ups Today -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">My Follow-ups Roster</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Overdue -->
            <div class="p-4 rounded-card border border-red-200 bg-red-50/50 space-y-2">
                <span class="text-xs font-bold text-red-700">Overdue ({{ $myOverdue->count() }})</span>
                @forelse($myOverdue as $f)
                    <div class="p-2 bg-surface rounded border border-red-200 text-xs flex items-center justify-between">
                        <div>
                            <p class="font-bold text-ink">{{ $f->lead?->name ?? 'Lead' }}</p>
                            <p class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($f->due_at)->format('M d, H:i') }}</p>
                        </div>
                        <button wire:click="markDone({{ $f->id }})" class="text-[10px] bg-red-600 text-white font-bold px-2 py-1 rounded">Mark Done</button>
                    </div>
                @empty
                    <p class="text-[11px] text-muted">No overdue follow-ups.</p>
                @endforelse
            </div>

            <!-- Today -->
            <div class="p-4 rounded-card border border-amber-200 bg-amber-50/50 space-y-2">
                <span class="text-xs font-bold text-amber-700">Due Today ({{ $myToday->count() }})</span>
                @forelse($myToday as $f)
                    <div class="p-2 bg-surface rounded border border-amber-200 text-xs flex items-center justify-between">
                        <div>
                            <p class="font-bold text-ink">{{ $f->lead?->name ?? 'Lead' }}</p>
                            <p class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($f->due_at)->format('H:i') }}</p>
                        </div>
                        <button wire:click="markDone({{ $f->id }})" class="text-[10px] bg-amber-600 text-white font-bold px-2 py-1 rounded">Mark Done</button>
                    </div>
                @empty
                    <p class="text-[11px] text-muted">No follow-ups due today.</p>
                @endforelse
            </div>

            <!-- Upcoming -->
            <div class="p-4 rounded-card border border-border bg-canvas space-y-2">
                <span class="text-xs font-bold text-ink">Upcoming ({{ $myUpcoming->count() }})</span>
                @forelse($myUpcoming as $f)
                    <div class="p-2 bg-surface rounded border border-border text-xs flex items-center justify-between">
                        <div>
                            <p class="font-bold text-ink">{{ $f->lead?->name ?? 'Lead' }}</p>
                            <p class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($f->due_at)->format('M d') }}</p>
                        </div>
                        <button wire:click="markDone({{ $f->id }})" class="text-[10px] bg-ink text-white font-bold px-2 py-1 rounded">Mark Done</button>
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
