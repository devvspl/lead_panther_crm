<div class="space-y-4">

    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Log Site Visit for {{ $lead->name }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Project Unit / Sample Flat</label>
                <livewire:shared.searchable-select :model="\App\Models\ProjectUnit::class" :searchable="true"
                    placeholder="Select Unit" wire:model="projectUnitId" key="site-unit" class="w-full mt-1" />
            </div>

            <div>
                <label class="font-bold text-ink">Visit Date & Time</label>
                <input type="datetime-local" wire:model="visitedAt"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Visit Outcome</label>
                <x-ui.themed-select wire:model="outcome" :options="['interested' => 'High Interest (Loved the layout)', 'needs_followup' => 'Needs Second Visit / Family Decision', 'booked' => 'Booked Spot Token', 'not_interested' => 'Not Interested (Budget Out-of-Range)']" placeholder="Visit Outcome"
                    searchable="true" class="w-full mt-1" />
            </div>

            <div class="flex items-center space-x-2 pt-6">
                <input type="checkbox" wire:model="attended" id="attended_cb" class="rounded border-border text-ink">
                <label for="attended_cb" class="font-bold text-ink">Lead Attended Site Visit</label>
            </div>

            <div class="md:col-span-2">
                <label class="font-bold text-ink">Sales Remarks & Feedback</label>
                <textarea wire:model="remarks" rows="3" placeholder="Client feedback during site tour..."
                    class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="logVisit" variant="primary" class="text-xs">
                Log Site Visit
            </x-ui.button>
        </div>
    </div>

    <!-- Stage Suggestion Modal -->
    @if($showStageSuggestModal)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4">
                <h3 class="text-sm font-bold text-ink">Move Lead Stage to "Site Visit"?</h3>
                <p class="text-xs text-muted">You logged a site visit for {{ $lead->name }}. Would you like to advance the
                    lead's stage to <strong>Site Visit</strong>?</p>

                <div class="flex justify-end space-x-2 pt-2">
                    <button wire:click="$set('showStageSuggestModal', false)"
                        class="text-xs text-muted hover:underline px-3 py-2">Keep Current Stage</button>
                    <x-ui.button wire:click="confirmUpdateStage" variant="primary" class="text-xs">
                        Update Stage to Site Visit
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>