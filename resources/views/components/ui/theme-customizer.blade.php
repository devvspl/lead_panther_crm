@php
    $theme = \App\Support\ThemeService::getUserTheme();
    $themeJson = json_encode($theme);
    $defaultsJson = json_encode(\App\Support\ThemeService::DEFAULTS);
@endphp

<div 
    x-data="{
        open: false,
        saving: false,
        resetting: false,
        theme: {{ $themeJson }},
        defaults: {{ $defaultsJson }},

        init() {
            this.applyThemeToDOM();

            window.addEventListener('open-theme-customizer', () => {
                this.open = true;
            });
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.open) {
                    this.open = false;
                }
            });
        },

        applyThemeToDOM() {
            const root = document.documentElement;
            root.style.setProperty('--theme-primary', this.theme.theme_primary_color);
            root.style.setProperty('--theme-secondary', this.theme.theme_secondary_color);
            root.style.setProperty('--theme-accent', this.theme.theme_accent_color);
            root.style.setProperty('--theme-sidebar-bg', this.theme.theme_sidebar_bg);
            root.style.setProperty('--theme-sidebar-text', this.theme.theme_sidebar_text);
            root.style.setProperty('--theme-active-menu-color', this.theme.theme_active_menu_color);
            root.style.setProperty('--theme-active-menu-bg', this.theme.theme_active_menu_bg);
            root.style.setProperty('--theme-header-bg', this.theme.theme_header_bg);
            root.style.setProperty('--theme-header-text', this.theme.theme_header_text);
            root.style.setProperty('--theme-page-bg', this.theme.theme_page_bg);
            root.style.setProperty('--theme-card-bg', this.theme.theme_card_bg);
            root.style.setProperty('--theme-border-color', this.theme.theme_border_color);
            root.style.setProperty('--theme-font-family', this.theme.theme_font_family);
            root.style.setProperty('--theme-font-size', this.theme.theme_font_size);
            root.style.setProperty('--theme-border-radius', this.theme.theme_border_radius);

            // Notify sidebar navigation active style handler
            localStorage.setItem('leadpanther_sidebar_active_style', this.theme.theme_active_menu_style === 'text_only' ? 'text-only' : 'highlighted');
            window.dispatchEvent(new CustomEvent('sidebar-style-changed', { detail: this.theme.theme_active_menu_style }));
        },

        onColorChange(key, value) {
            this.theme[key] = value;
            this.applyThemeToDOM();
        },

        applyPreset(preset) {
            this.theme = { ...this.theme, ...preset };
            this.applyThemeToDOM();
        },

        saveTheme() {
            this.saving = true;
            fetch('{{ route('settings.theme.update') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.theme)
            })
            .then(res => res.json())
            .then(data => {
                this.saving = false;
                if (data.success) {
                    this.theme = data.theme;
                    this.applyThemeToDOM();
                    window.dispatchEvent(new CustomEvent('toast-alert', { 
                        detail: { type: 'success', message: data.message || 'Theme saved successfully!' } 
                    }));
                } else {
                    window.dispatchEvent(new CustomEvent('toast-alert', { 
                        detail: { type: 'error', message: 'Failed to save theme customizations.' } 
                    }));
                }
            })
            .catch(err => {
                this.saving = false;
                window.dispatchEvent(new CustomEvent('toast-alert', { 
                    detail: { type: 'error', message: 'Error saving theme settings.' } 
                }));
            });
        },

        resetTheme() {
            const self = this;
            window.dispatchEvent(new CustomEvent('confirm-action', {
                detail: {
                    title: 'Reset Theme Settings?',
                    message: 'This will remove all your custom theme customizations and restore the default system appearance. You can customize the theme again at any time.',
                    confirmText: 'Yes, Reset Theme',
                    cancelText: 'Cancel',
                    variant: 'warning',
                    onConfirm: function() {
                        self.resetting = true;
                        return fetch('{{ route('settings.theme.reset') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        })
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            self.resetting = false;
                            if (data.success) {
                                self.theme = Object.assign({}, self.defaults);
                                self.applyThemeToDOM();
                                window.dispatchEvent(new CustomEvent('toast-alert', { 
                                    detail: { type: 'info', message: data.message || 'Theme reset to defaults!' } 
                                }));
                            }
                        })
                        .catch(function(err) {
                            self.resetting = false;
                            window.dispatchEvent(new CustomEvent('toast-alert', { 
                                detail: { type: 'error', message: 'Error resetting theme.' } 
                            }));
                        });
                    }
                }
            }));
        }
    }"
    class="relative z-50"
>
    <!-- Offcanvas Backdrop Overlay -->
    <div 
        x-show="open"
        x-cloak
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="open = false"
        class="fixed inset-0 bg-black/40 backdrop-blur-[2px] z-50 transition-opacity"
        style="display: none;"
    ></div>

    <!-- Offcanvas Panel (Slide in from Right) -->
    <div 
        x-show="open"
        x-cloak
        x-transition:enter="transform transition ease-in-out duration-300 sm:duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-300 sm:duration-300"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 max-w-full flex pl-10 z-50 pointer-events-auto"
        style="display: none;"
    >
        <div class="w-screen max-w-md sm:max-w-lg bg-surface border-l border-border shadow-2xl flex flex-col justify-between overflow-hidden">
            
            <!-- Offcanvas Header -->
            <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-canvas/30">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-lg bg-ink text-white flex items-center justify-center shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="13.5" cy="6.5" r=".5" fill="currentColor" />
                            <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" />
                            <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" />
                            <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" />
                            <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-ink">Theme Customizer</h3>
                        <p class="text-[11px] text-muted">Customize colors, typography & layout live</p>
                    </div>
                </div>

                <button 
                    x-on:click="open = false"
                    type="button" 
                    class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition cursor-pointer"
                    aria-label="Close theme customizer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Scrollable Body with Live Preview & Settings -->
            <div class="flex-1 overflow-y-auto overflow-x-hidden p-5 space-y-6 sidebar-scroll">
                
                <!-- Live Mini Preview Box -->
                <div class="p-3.5 bg-canvas/70 rounded-xl border border-border space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-muted uppercase tracking-wider">Live Preview</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-pill bg-ink text-white font-mono">Real-time</span>
                    </div>

                    <!-- Mini Mockup Interface -->
                    <div 
                        class="rounded-lg border overflow-hidden text-xs shadow-xs transition-colors duration-200"
                        :style="{
                            backgroundColor: theme.theme_page_bg,
                            borderColor: theme.theme_border_color,
                            fontFamily: theme.theme_font_family
                        }"
                    >
                        <!-- Mini Header -->
                        <div 
                            class="h-8 px-3 border-b flex items-center justify-between transition-colors duration-200"
                            :style="{
                                backgroundColor: theme.theme_header_bg,
                                color: theme.theme_header_text,
                                borderColor: theme.theme_border_color
                            }"
                        >
                            <div class="flex items-center space-x-1.5 font-bold text-[11px]">
                                <span class="w-4 h-4 rounded bg-ink text-white text-[9px] flex items-center justify-center font-bold">LP</span>
                                <span>Lead Panther</span>
                            </div>
                            <div class="w-5 h-5 rounded-full bg-accent text-white text-[9px] flex items-center justify-center font-bold">AD</div>
                        </div>

                        <!-- Mini Body: Sidebar + Content -->
                        <div class="flex min-h-[110px]">
                            <!-- Mini Sidebar -->
                            <div 
                                class="w-24 p-2 border-r space-y-1.5 flex flex-col justify-between transition-colors duration-200"
                                :style="{
                                    backgroundColor: theme.theme_sidebar_bg,
                                    borderColor: theme.theme_border_color,
                                    color: theme.theme_sidebar_text
                                }"
                            >
                                <div class="space-y-1">
                                    <!-- Active Item -->
                                    <div 
                                        class="px-2 py-1 text-[10px] font-bold rounded transition-colors duration-200 flex items-center space-x-1"
                                        :style="{
                                            backgroundColor: theme.theme_active_menu_style === 'text_only' ? 'transparent' : theme.theme_active_menu_bg,
                                            color: theme.theme_active_menu_color,
                                            borderRadius: theme.theme_border_radius
                                        }"
                                    >
                                        <span>•</span>
                                        <span>Leads</span>
                                    </div>
                                    <!-- Inactive Item -->
                                    <div class="px-2 py-1 text-[10px] rounded opacity-70 flex items-center space-x-1">
                                        <span>•</span>
                                        <span>Analytics</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Mini Main Canvas -->
                            <div class="flex-1 p-2.5 space-y-2">
                                <div 
                                    class="p-2 rounded border shadow-2xs transition-colors duration-200"
                                    :style="{
                                        backgroundColor: theme.theme_card_bg,
                                        borderColor: theme.theme_border_color,
                                        borderRadius: theme.theme_border_radius
                                    }"
                                >
                                    <div class="flex justify-between items-center text-[10px] mb-1">
                                        <span class="font-bold text-ink">Total Leads</span>
                                        <span class="text-success font-semibold">+14%</span>
                                    </div>
                                    <div class="text-sm font-bold text-ink">1,482</div>
                                </div>

                                <button 
                                    type="button" 
                                    class="w-full py-1 text-[10px] font-bold text-white shadow-2xs flex items-center justify-center transition-colors duration-200"
                                    :style="{
                                        backgroundColor: theme.theme_primary_color,
                                        borderRadius: theme.theme_border_radius
                                    }"
                                >
                                    Action Button
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Theme Presets Selection -->
                <div class="space-y-2">
                    <label class="text-xs font-bold text-ink uppercase tracking-wider">Quick Presets</label>
                    <div class="grid grid-cols-2 gap-2">
                        <!-- Clean Default Light -->
                        <button 
                            type="button"
                            x-on:click="applyPreset({
                                theme_primary_color: '#111827',
                                theme_secondary_color: '#4B5563',
                                theme_accent_color: '#111827',
                                theme_sidebar_bg: '#FFFFFF',
                                theme_sidebar_text: '#6B7280',
                                theme_active_menu_style: 'highlight',
                                theme_active_menu_color: '#0A0A0A',
                                theme_active_menu_bg: '#F5F5F5',
                                theme_header_bg: '#FFFFFF',
                                theme_header_text: '#0A0A0A',
                                theme_page_bg: '#F5F5F5',
                                theme_card_bg: '#FFFFFF',
                                theme_border_color: '#E5E7EB'
                            })"
                            class="p-2 border border-border rounded-lg text-left hover:border-ink transition bg-white text-xs space-y-1 cursor-pointer"
                        >
                            <div class="flex items-center space-x-1">
                                <span class="w-3 h-3 rounded-full bg-[#111827]"></span>
                                <span class="w-3 h-3 rounded-full bg-[#F5F5F5] border border-border"></span>
                                <span class="w-3 h-3 rounded-full bg-[#FFFFFF] border border-border"></span>
                            </div>
                            <div class="font-semibold text-ink text-[11px]">Clean Light</div>
                        </button>

                        <!-- Emerald Fresh -->
                        <button 
                            type="button"
                            x-on:click="applyPreset({
                                theme_primary_color: '#059669',
                                theme_secondary_color: '#4B5563',
                                theme_accent_color: '#047857',
                                theme_sidebar_bg: '#F0FDF4',
                                theme_sidebar_text: '#166534',
                                theme_active_menu_style: 'highlight',
                                theme_active_menu_color: '#064E3B',
                                theme_active_menu_bg: '#DCFCE7',
                                theme_header_bg: '#FFFFFF',
                                theme_header_text: '#064E3B',
                                theme_page_bg: '#F8FAFC',
                                theme_card_bg: '#FFFFFF',
                                theme_border_color: '#E2E8F0'
                            })"
                            class="p-2 border border-border rounded-lg text-left hover:border-emerald-600 transition bg-emerald-50 text-xs space-y-1 cursor-pointer"
                        >
                            <div class="flex items-center space-x-1">
                                <span class="w-3 h-3 rounded-full bg-[#059669]"></span>
                                <span class="w-3 h-3 rounded-full bg-[#DCFCE7]"></span>
                                <span class="w-3 h-3 rounded-full bg-[#FFFFFF]"></span>
                            </div>
                            <div class="font-semibold text-emerald-900 text-[11px]">Emerald Fresh</div>
                        </button>
                    </div>
                </div>

                <!-- Section: Brand & Action Colors -->
                <div class="space-y-3 pt-3 border-t border-border">
                    <label class="text-xs font-bold text-ink uppercase tracking-wider">Brand & Action Colors</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Primary Color -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Primary Color</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_primary_color"
                                    x-on:input="onColorChange('theme_primary_color', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_primary_color"
                                    x-on:input="onColorChange('theme_primary_color', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>

                        <!-- Secondary Color -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Secondary Color</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_secondary_color"
                                    x-on:input="onColorChange('theme_secondary_color', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_secondary_color"
                                    x-on:input="onColorChange('theme_secondary_color', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>

                        <!-- Accent Color -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Accent Color</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_accent_color"
                                    x-on:input="onColorChange('theme_accent_color', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_accent_color"
                                    x-on:input="onColorChange('theme_accent_color', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Sidebar & Navigation -->
                <div class="space-y-3 pt-3 border-t border-border">
                    <label class="text-xs font-bold text-ink uppercase tracking-wider">Sidebar Navigation</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Sidebar Background -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Sidebar Background</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_sidebar_bg"
                                    x-on:input="onColorChange('theme_sidebar_bg', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_sidebar_bg"
                                    x-on:input="onColorChange('theme_sidebar_bg', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>

                        <!-- Sidebar Text Color -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Sidebar Text / Inactive</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_sidebar_text"
                                    x-on:input="onColorChange('theme_sidebar_text', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_sidebar_text"
                                    x-on:input="onColorChange('theme_sidebar_text', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Active Menu Style Segmented Option -->
                    <div class="space-y-2 pt-2">
                        <span class="text-xs font-bold text-ink">Active Menu Style</span>
                        <div class="grid grid-cols-2 gap-2">
                            <!-- Option 1: Text Only -->
                            <button 
                                type="button"
                                x-on:click="onColorChange('theme_active_menu_style', 'text_only')"
                                :class="theme.theme_active_menu_style === 'text_only' ? 'border-ink bg-ink text-white font-bold shadow-xs' : 'border-border bg-canvas text-ink hover:bg-surface'"
                                class="p-2.5 text-xs rounded-lg border text-left transition cursor-pointer space-y-1"
                            >
                                <div class="font-bold flex items-center justify-between">
                                    <span>1. Text Only</span>
                                    <span x-show="theme.theme_active_menu_style === 'text_only'">✓</span>
                                </div>
                                <div class="text-[11px] opacity-80 leading-tight">No background. Solid active text & icon.</div>
                            </button>

                            <!-- Option 2: Background Highlight -->
                            <button 
                                type="button"
                                x-on:click="onColorChange('theme_active_menu_style', 'highlight')"
                                :class="theme.theme_active_menu_style === 'highlight' ? 'border-ink bg-ink text-white font-bold shadow-xs' : 'border-border bg-canvas text-ink hover:bg-surface'"
                                class="p-2.5 text-xs rounded-lg border text-left transition cursor-pointer space-y-1"
                            >
                                <div class="font-bold flex items-center justify-between">
                                    <span>2. Highlight BG</span>
                                    <span x-show="theme.theme_active_menu_style === 'highlight'">✓</span>
                                </div>
                                <div class="text-[11px] opacity-80 leading-tight">Subtle highlighted background pill.</div>
                            </button>
                        </div>
                    </div>

                    <!-- Active Menu Colors -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                        <!-- Active Menu Text/Icon Color -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Active Text/Icon Color</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_active_menu_color"
                                    x-on:input="onColorChange('theme_active_menu_color', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_active_menu_color"
                                    x-on:input="onColorChange('theme_active_menu_color', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>

                        <!-- Active Menu Background Color (Hidden/Disabled when text_only is selected) -->
                        <div 
                            class="space-y-1 transition-opacity duration-200"
                            :class="theme.theme_active_menu_style === 'text_only' ? 'opacity-40 pointer-events-none' : 'opacity-100'"
                        >
                            <span class="text-xs font-medium text-ink flex items-center justify-between">
                                <span>Active Background</span>
                                <span x-show="theme.theme_active_menu_style === 'text_only'" class="text-[10px] text-muted">(Disabled in Text Only)</span>
                            </span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_active_menu_bg"
                                    x-on:input="onColorChange('theme_active_menu_bg', $event.target.value)"
                                    :disabled="theme.theme_active_menu_style === 'text_only'"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_active_menu_bg"
                                    x-on:input="onColorChange('theme_active_menu_bg', $event.target.value)"
                                    :disabled="theme.theme_active_menu_style === 'text_only'"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Header Navigation -->
                <div class="space-y-3 pt-3 border-t border-border">
                    <label class="text-xs font-bold text-ink uppercase tracking-wider">Top Header</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Header Background -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Header Background</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_header_bg"
                                    x-on:input="onColorChange('theme_header_bg', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_header_bg"
                                    x-on:input="onColorChange('theme_header_bg', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>

                        <!-- Header Text Color -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Header Text Color</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_header_text"
                                    x-on:input="onColorChange('theme_header_text', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_header_text"
                                    x-on:input="onColorChange('theme_header_text', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Page & Surface Backgrounds -->
                <div class="space-y-3 pt-3 border-t border-border">
                    <label class="text-xs font-bold text-ink uppercase tracking-wider">Page & Card Surfaces</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- Page Background -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Page Background</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_page_bg"
                                    x-on:input="onColorChange('theme_page_bg', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_page_bg"
                                    x-on:input="onColorChange('theme_page_bg', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>

                        <!-- Card Background -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Card Background</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_card_bg"
                                    x-on:input="onColorChange('theme_card_bg', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_card_bg"
                                    x-on:input="onColorChange('theme_card_bg', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>

                        <!-- Border Color -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Border Color</span>
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="color" 
                                    :value="theme.theme_border_color"
                                    x-on:input="onColorChange('theme_border_color', $event.target.value)"
                                    class="h-8 w-10 p-0 border border-border rounded cursor-pointer bg-transparent"
                                />
                                <input 
                                    type="text" 
                                    x-model="theme.theme_border_color"
                                    x-on:input="onColorChange('theme_border_color', $event.target.value)"
                                    class="h-8 flex-1 text-xs px-2.5 border border-border rounded-lg font-mono focus:ring-1 focus:ring-ink"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Typography & Layout -->
                <div class="space-y-3 pt-3 border-t border-border">
                    <label class="text-xs font-bold text-ink uppercase tracking-wider">Typography & Layout</label>
                    
                    <!-- Font Family -->
                    <div class="space-y-1">
                        <span class="text-xs font-medium text-ink">Font Family</span>
                        <select 
                            x-model="theme.theme_font_family"
                            x-on:change="onColorChange('theme_font_family', $event.target.value)"
                            class="w-full h-9 text-xs px-3 bg-surface text-ink border border-border rounded-lg focus:ring-1 focus:ring-ink"
                        >
                            <option value="Inter, sans-serif">Inter (Modern Clean)</option>
                            <option value="'Roboto', sans-serif">Roboto</option>
                            <option value="'Outfit', sans-serif">Outfit (Geometric Brand)</option>
                            <option value="'Plus Jakarta Sans', sans-serif">Plus Jakarta Sans</option>
                            <option value="'Poppins', sans-serif">Poppins</option>
                            <option value="system-ui, -apple-system, sans-serif">System UI</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Font Size -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Base Font Size</span>
                            <select 
                                x-model="theme.theme_font_size"
                                x-on:change="onColorChange('theme_font_size', $event.target.value)"
                                class="w-full h-9 text-xs px-3 bg-surface text-ink border border-border rounded-lg focus:ring-1 focus:ring-ink"
                            >
                                <option value="13px">Compact (13px)</option>
                                <option value="14px">Standard (14px)</option>
                                <option value="15px">Comfortable (15px)</option>
                                <option value="16px">Large (16px)</option>
                            </select>
                        </div>

                        <!-- Border Radius -->
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-ink">Border Radius</span>
                            <select 
                                x-model="theme.theme_border_radius"
                                x-on:change="onColorChange('theme_border_radius', $event.target.value)"
                                class="w-full h-9 text-xs px-3 bg-surface text-ink border border-border rounded-lg focus:ring-1 focus:ring-ink"
                            >
                                <option value="0px">Sharp (0px)</option>
                                <option value="0.25rem">Small (4px)</option>
                                <option value="0.5rem">Medium (8px)</option>
                                <option value="0.75rem">Large (12px)</option>
                                <option value="1rem">Extra Large (16px)</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Offcanvas Footer Actions -->
            <div class="p-4 border-t border-border bg-canvas/40 flex items-center justify-between gap-2.5">
                <button 
                    type="button" 
                    x-on:click="resetTheme()"
                    :disabled="resetting"
                    class="px-3.5 py-2 text-xs font-semibold text-danger hover:bg-danger/10 rounded-lg border border-danger/20 transition cursor-pointer"
                >
                    <span x-show="!resetting">Reset Defaults</span>
                    <span x-show="resetting" class="flex items-center space-x-1">
                        <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Resetting...</span>
                    </span>
                </button>

                <div class="flex items-center space-x-2">
                    <button 
                        type="button" 
                        x-on:click="open = false"
                        class="px-3.5 py-2 text-xs font-semibold text-ink hover:bg-canvas rounded-lg border border-border transition cursor-pointer"
                    >
                        Close
                    </button>
                    <button 
                        type="button" 
                        x-on:click="saveTheme()"
                        :disabled="saving"
                        class="px-4 py-2 text-xs font-bold text-white bg-ink hover:bg-black rounded-lg shadow-sm transition cursor-pointer flex items-center space-x-1.5"
                    >
                        <svg x-show="!saving" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <svg x-show="saving" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
