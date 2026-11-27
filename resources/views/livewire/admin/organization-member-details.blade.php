<div>
    <x-modal name="org-member-details" :show="$show" maxWidth="3xl">
        <div x-init="$watch('show', value => { if (!value && $wire.show) $wire.close() })" class="flex flex-col max-h-[calc(100dvh-4rem)]">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 p-4 sm:p-6 border-b border-border shrink-0 bg-surface">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-canvas border border-border flex items-center justify-center shrink-0 text-ink font-bold text-sm">
                        {{ strtoupper(substr($name ?: 'U', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base sm:text-lg font-bold text-ink truncate">Team Member Details</h2>
                        <div class="text-xs text-muted font-medium flex flex-wrap items-center gap-2 truncate mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md font-mono text-[11px] font-bold bg-primary/10 border border-primary/20 text-primary shrink-0">
                                {{ $userCode }}
                            </span>
                            <span class="text-ink font-semibold truncate">{{ $name }}</span>
                            <span>·</span>
                            <span class="text-muted truncate">{{ $email }}</span>
                            @if($joinedDate)
                                <span>·</span>
                                <span class="text-muted shrink-0">Joined {{ $joinedDate }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <button type="button" wire:click="close" aria-label="Close member details" class="inline-flex items-center justify-center rounded-lg p-2 text-muted hover:text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-primary cursor-pointer transition">
                    <x-ui.team-icon name="close" />
                </button>
            </div>

            <!-- Body / Main Content Area: Only Editable Form Inputs -->
            <div class="min-h-0 overflow-y-auto overscroll-contain p-4 sm:p-6 space-y-6">
                @if($member)
                    <form id="org-member-details-form" wire:submit="saveDetails">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Full Name -->
                            <div>
                                <x-input-label for="member-name" value="Full Name" class="text-xs font-semibold text-ink mb-1.5" />
                                <x-text-input id="member-name" variant="compact" wire:model="name" class="w-full h-9" placeholder="Enter member name" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <!-- Email Address -->
                            <div>
                                <x-input-label for="member-email" value="Email Address" class="text-xs font-semibold text-ink mb-1.5" />
                                <x-text-input id="member-email" type="email" variant="compact" wire:model="email" class="w-full h-9" placeholder="email@example.com" />
                                <x-input-error :messages="$errors->get('email')" class="mt-1" />
                            </div>

                            <!-- Phone Number -->
                            <div>
                                <x-input-label for="member-phone" value="Phone Number" class="text-xs font-semibold text-ink mb-1.5" />
                                <x-text-input id="member-phone" variant="compact" wire:model="phone" class="w-full h-9" placeholder="e.g. +91 98765 43210" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                            </div>

                            <!-- Role (After Phone Number) -->
                            <div>
                                <x-input-label for="member-role" value="Role" class="text-xs font-semibold text-ink mb-1.5" />
                                <x-ui.themed-select id="member-role" wire:model.live="role" :options="$this->availableRoles" class="w-full" triggerClass="h-9" />
                                <x-input-error :messages="$errors->get('role')" class="mt-1" />
                            </div>

                            <!-- Team Leader (Only for Executives) or Team Name (Only for Team Leaders) -->
                            @if($this->isExecutive)
                                <div>
                                    <x-input-label for="member-team-leader" value="Team Leader" class="text-xs font-semibold text-ink mb-1.5" />
                                    <x-ui.themed-select id="member-team-leader" wire:model.live="teamLeaderId" :options="$this->availableLeaders" placeholder="Choose Team Leader" class="w-full" triggerClass="h-9" />
                                    <x-input-error :messages="$errors->get('teamLeaderId')" class="mt-1" />
                                </div>
                            @elseif($this->isTeamLeader)
                                <div>
                                    <x-input-label for="member-team-name" value="Team Name" class="text-xs font-semibold text-ink mb-1.5" />
                                    <x-text-input id="member-team-name" variant="compact" wire:model="teamName" class="w-full h-9" placeholder="Enter team name" />
                                    <x-input-error :messages="$errors->get('teamName')" class="mt-1" />
                                </div>
                            @endif

                            <!-- Account Status -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <x-input-label for="member-status" value="Account Status" class="text-xs font-semibold text-ink" />
                                </div>
                                <x-ui.themed-select id="member-status" wire:model.live="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" class="w-full" triggerClass="h-9" />
                                <x-input-error :messages="$errors->get('status')" class="mt-1" />
                            </div>
                        </div>

                        <!-- Inline Password Section -->
                        @if($changingPassword)
                            <div class="mt-5 pt-4 border-t border-border space-y-3 bg-canvas/40 -mx-4 -mb-4 sm:-mx-6 sm:-mb-6 p-4 sm:p-6 rounded-b-xl">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Change Password</h3>
                                        <p class="text-[11px] text-muted">Set a new password for this user directly.</p>
                                    </div>
                                    <button type="button" wire:click="$set('changingPassword', false)" class="text-xs text-muted hover:text-ink underline cursor-pointer">Cancel</button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="member-new-password" value="New Password" class="text-xs font-semibold text-ink mb-1" />
                                        <x-ui.password-input id="member-new-password" wire:model="password" autocomplete="new-password" placeholder="Min. 8 characters" />
                                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="member-confirm-password" value="Confirm Password" class="text-xs font-semibold text-ink mb-1" />
                                        <x-ui.password-input id="member-confirm-password" wire:model="password_confirmation" autocomplete="new-password" placeholder="Repeat new password" />
                                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                                    </div>
                                </div>
                            </div>
                        @endif
                    </form>
                @endif
            </div>

            <!-- Footer: Clean Action Bar -->
            <footer class="shrink-0 border-t border-border bg-canvas/40 px-4 sm:px-6 py-3.5 flex flex-wrap sm:flex-nowrap items-center justify-between gap-3">
                @if($member)
                    <!-- Left: Member Utilities (Suspend, Send Login, Password) -->
                    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                        <button type="button" wire:click="toggleStatus" wire:loading.attr="disabled"
                            class="px-3.5 py-2 border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold rounded-lg transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            @if($status === 'active')
                                <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Suspend Account</span>
                            @else
                                <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Activate Account</span>
                            @endif
                        </button>

                        <button type="button" wire:click="sendLoginDetails" wire:loading.attr="disabled" wire:target="sendLoginDetails"
                            class="px-3.5 py-2 border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold rounded-lg transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span wire:loading.remove wire:target="sendLoginDetails">Send Login Details</span>
                            <span wire:loading wire:target="sendLoginDetails">Sending...</span>
                        </button>

                        <button type="button" wire:click="$toggle('changingPassword')"
                            class="px-3.5 py-2 border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold rounded-lg transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer {{ $changingPassword ? 'bg-canvas ring-1 ring-primary/40' : '' }}">
                            <svg class="w-3.5 h-3.5 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <span>{{ $changingPassword ? 'Hide Password' : 'Change Password' }}</span>
                        </button>
                    </div>

                    <!-- Right: Confirmation Actions (Cancel, Save) -->
                    <div class="flex items-center gap-2.5 shrink-0">
                        <button type="button" wire:click="close"
                            class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                            Cancel
                        </button>

                        <button type="submit" form="org-member-details-form" wire:loading.attr="disabled"
                            class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveDetails">Save Changes</span>
                            <span wire:loading wire:target="saveDetails">Saving...</span>
                        </button>
                    </div>
                @endif
            </footer>
        </div>
    </x-modal>
</div>
