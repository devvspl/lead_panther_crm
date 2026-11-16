<section class="bg-surface border border-border rounded-xl p-5 sm:p-6 space-y-5">
    <div><h2 class="text-sm font-bold text-ink">Notifications & Email Design</h2><p class="text-xs text-muted mt-1">System-wide settings for all panels. Notification category preferences still apply to each recipient.</p></div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
        <div class="border border-border rounded-lg p-3"><strong>In-app notifications</strong><p class="text-muted mt-1">Database inbox and polling enabled. Broadcasting: {{ $broadcastEnabled ? 'configured' : 'not configured' }}.</p></div>
        <div class="border border-border rounded-lg p-3"><strong>Browser push</strong><p class="text-muted mt-1">Not configured. Notifications outside the open app require a push provider and browser subscription.</p></div>
        <div class="border border-border rounded-lg p-3"><strong>Email transport</strong><p class="text-muted mt-1">{{ $mailer }} {{ in_array($mailer, ['log', 'array']) ? '(does not deliver to mailboxes)' : '(configured; use test to verify)' }}. Queue: {{ $queueDriver }}.</p>@if($pendingEmailJobs !== null)<p class="text-muted mt-1">{{ $pendingEmailJobs }} event email jobs waiting in the queue.</p>@endif</div>
    </div>
    <form wire:submit="save" class="space-y-5">
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="emailEnabled" class="rounded border-border"> Enable email for notification events</label>
        <p class="text-xs text-muted">Password reset and account verification emails remain available independently of this event notification switch.</p>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @foreach($designs as $key => $label)
                <div class="border rounded-xl overflow-hidden {{ $activeDesign === $key ? 'border-primary' : 'border-border' }}" wire:key="email-design-{{ $key }}">
                    <label class="flex items-center gap-2 p-3 text-sm font-semibold"><input type="radio" wire:model.live="activeDesign" value="{{ $key }}">{{ $label }}</label>
                    <iframe title="{{ $label }} email preview" sandbox="" class="w-full h-[540px] border-t border-border" srcdoc="{{ view('emails.notification', ['heading' => 'Your workspace update', 'messageBody' => 'Hello '.auth()->user()->name.', this preview shows how updates from your Lead Panther workspace will appear in your inbox.', 'actionUrl' => url('/notifications'), 'design' => $key])->render() }}"></iframe>
                </div>
            @endforeach
        </div>
        @error('activeDesign')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @error('emailEnabled')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <div class="flex flex-wrap gap-3">
            <button type="submit" wire:loading.attr="disabled" class="bg-ink text-white rounded-lg px-4 py-2 text-xs font-semibold">Save Notification Settings</button>
            <button type="button" wire:click="sendTest" wire:loading.attr="disabled" class="border border-border rounded-lg px-4 py-2 text-xs font-semibold">Send Test to My Email</button>
        </div>
        <p class="text-xs text-muted">Test emails send immediately using the last saved settings. Event emails need a running queue worker. Sent means accepted by the mail transport, not confirmed inbox delivery.</p>
    </form>
    <div class="space-y-3" wire:poll.15s>
        <h3 class="text-sm font-semibold">Recent Email Delivery Attempts</h3>
        <x-ui.advanced-table
            :columns="[
                ['key' => 'id', 'label' => 'Log ID', 'sortable' => false],
                ['key' => 'notifiable_id', 'label' => 'Recipient ID', 'sortable' => false],
                ['key' => 'status', 'label' => 'Status', 'sortable' => false],
                ['key' => 'sent_at', 'label' => 'Sent At', 'sortable' => false],
                ['key' => 'retries', 'label' => 'Failed Attempts', 'sortable' => false],
            ]"
            :rows="$deliveryLogs" :showSearch="false" :showFilterDropdown="false" :showConfigurations="false"
            tableName="email-delivery-attempts" emptyTitle="No Email Attempts" emptyMessage="Delivery results will appear after an email job runs."
        />
    </div>
</section>
