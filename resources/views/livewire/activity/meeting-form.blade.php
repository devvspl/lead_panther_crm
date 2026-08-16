<div class="space-y-4">

    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Schedule Meeting for {{ $lead->name }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Date & Time</label>
                <input type="datetime-local" wire:model="scheduledAt"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Meeting Mode</label>
                <x-ui.themed-select wire:model="meetingType" :options="['in_person' => 'In Person', 'virtual_zoom' => 'Virtual Google Meet / Zoom', 'site_office' => 'Site Experience Center']" placeholder="Meeting Mode"
                    class="w-full mt-1" />
            </div>

            <div class="md:col-span-2">
                <label class="font-bold text-ink">Location / Link</label>
                <input type="text" wire:model="location" placeholder="e.g. Bandra Sales Office or Google Meet Link"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1">
            </div>

            <div class="md:col-span-2">
                <label class="font-bold text-ink">Select Participants</label>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    @foreach($executives as $e)
                        <label class="flex items-center space-x-2 text-xs text-ink">
                            <input type="checkbox" wire:model="participantIds" value="{{ $e->id }}">
                            <span>{{ $e->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="font-bold text-ink">Agenda & Notes</label>
                <textarea wire:model="notes" rows="3" placeholder="Key topics to discuss..."
                    class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="scheduleMeeting" variant="primary" class="text-xs">
                Schedule Meeting & Update Stage
            </x-ui.button>
        </div>
    </div>
</div>