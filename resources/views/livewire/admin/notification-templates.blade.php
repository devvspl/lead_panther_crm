<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Notification Message Templates</h1>
            <p class="text-xs text-muted">Customize multi-channel message copy with token placeholders (e.g. &#123;&#123;lead_name&#125;&#125;).</p>
        </div>
    </div>



    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Template List Table Component -->
        <div class="lg:col-span-2 space-y-2">
            <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Configured Message Templates</h2>

            <x-ui.advanced-table 
                :columns="$this->tableColumns()"
                :rows="$templates"
                :visibleColumns="$visibleColumns"
                :sortField="$sortField"
                :sortDirection="$sortDirection"
                :quickFilters="[
                    ['key' => 'all', 'label' => 'All Channels'],
                    ['key' => 'whatsapp', 'label' => 'WhatsApp'],
                    ['key' => 'email', 'label' => 'Email'],
                    ['key' => 'sms', 'label' => 'SMS'],
                ]"
                :activeStatus="$statusFilter"
                searchPlaceholder="Search key, channel, body..."
                emptyTitle="No Templates Found"
                emptyMessage="No notification templates defined yet. Default fallback copy will be used."
            />
        </div>

        <!-- Template Form -->
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
            <h2 class="text-xs font-bold text-ink uppercase tracking-wider">{{ $selectedId ? 'Edit Template' : 'Create New Template' }}</h2>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="font-bold text-ink">Template Key</label>
                    <input type="text" wire:model="key" placeholder="e.g. lead_assigned_whatsapp" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                </div>

                <div>
                    <label class="font-bold text-ink">Channel</label>
                    <x-ui.themed-select 
                        wire:model="channel"
                        :options="['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email', 'database' => 'In-App Push']"
                        placeholder="Channel"
                        class="w-full mt-1"
                    />
                </div>

                <div>
                    <label class="font-bold text-ink">Subject (Optional)</label>
                    <input type="text" wire:model="subject" placeholder="Email / Push Subject" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                </div>

                <div>
                    <label class="font-bold text-ink">Message Body</label>
                    <textarea wire:model="body" rows="4" placeholder="Hello @{{lead_name}}, your lead code is @{{lead_code}}." class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
                    <span class="text-[10px] text-muted">Available Tokens: &#123;&#123;lead_name&#125;&#125;, &#123;&#123;lead_code&#125;&#125;, &#123;&#123;assigned_to&#125;&#125;</span>
                </div>

                <div class="flex items-center space-x-2 pt-2">
                    <x-ui.button wire:click="saveTemplate" variant="primary" class="w-full text-xs py-2">
                        Save Template
                    </x-ui.button>

                    @if($selectedId)
                        <button wire:click="resetForm" class="text-xs text-muted hover:underline px-3 py-2">Cancel</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
