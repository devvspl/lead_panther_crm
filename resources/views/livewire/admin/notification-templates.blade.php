<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Notification Message Templates</h1>
            <p class="text-xs text-muted">Customize multi-channel message copy with token placeholders (e.g. &#123;&#123;lead_name&#125;&#125;).</p>
        </div>
    </div>



    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Template List Table -->
        <div class="lg:col-span-2 bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
            <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Configured Message Templates</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-ink border-collapse">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                            <th class="py-3 px-4">Template Key</th>
                            <th class="py-3 px-4">Channel</th>
                            <th class="py-3 px-4">Body Snippet</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($templates as $tpl)
                            <tr class="hover:bg-canvas/50 transition">
                                <td class="py-3 px-4 font-bold text-ink">{{ $tpl->key }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-canvas text-ink border border-border">
                                        {{ $tpl->channel }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-muted truncate max-w-xs">{{ Str::limit($tpl->body, 50) }}</td>
                                <td class="py-3 px-4 text-right">
                                    <button wire:click="editTemplate({{ $tpl->id }})" class="text-xs font-semibold text-accent hover:underline">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-muted">No custom templates defined yet. Default fallback copy will be used.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-2">
                {{ $templates->links('vendor.pagination.tailwind') }}
            </div>
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
