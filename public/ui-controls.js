/* Shared presentation behavior. No application requests or business rules. */
(() => {
    if (window.LeadPantherUI) return;
    const locks = new Set();
    const ui = window.LeadPantherUI = {
        lock(owner, active) {
            active ? locks.add(owner) : locks.delete(owner);
            document.documentElement.classList.toggle('lp-overlay-open', locks.size > 0);
        },
        position(trigger, panel, preferredWidth, align = 'left') {
            const rect = trigger.getBoundingClientRect();
            const viewport = window.visualViewport;
            const width = viewport?.width || window.innerWidth;
            const height = viewport?.height || window.innerHeight;
            const offsetX = viewport?.offsetLeft || 0;
            const offsetY = viewport?.offsetTop || 0;
            const panelWidth = Math.min(Math.max(preferredWidth || rect.width, rect.width), width - 16);
            panel.style.width = panelWidth + 'px';
            const below = height + offsetY - rect.bottom - 12;
            const above = rect.top - offsetY - 12;
            const upwards = below < Math.min(panel.scrollHeight || 240, 240) && above > below;
            const available = Math.min(height - 16, Math.max(64, upwards ? above : below));
            const panelHeight = Math.min(panel.scrollHeight || 320, available);
            return {
                position: 'fixed', width: panelWidth + 'px',
                left: Math.max(offsetX + 8, Math.min(align === 'right' ? rect.right - panelWidth : rect.left, offsetX + width - panelWidth - 8)) + 'px',
                top: Math.max(offsetY + 8, Math.min(upwards ? rect.top - panelHeight - 4 : rect.bottom + 4, offsetY + height - panelHeight - 8)) + 'px',
                maxHeight: available + 'px',
            };
        },
        syncTheme() {
            const root = document.documentElement;
            const style = getComputedStyle(root);
            for (const [token, source] of Object.entries({primary:'primary', accent:'accent', surface:'card-bg', canvas:'page-bg', ink:'header-text', muted:'secondary', border:'border-color'})) {
                let hex = style.getPropertyValue('--theme-' + source).trim().replace('#','');
                if (hex.length === 3) hex = [...hex].map(c => c + c).join('');
                if (/^[\da-f]{6}$/i.test(hex)) root.style.setProperty('--lp-' + token + '-rgb', [0,2,4].map(i => parseInt(hex.slice(i,i+2),16)).join(' '));
            }
        },
    };

    window.leadPantherSelect = config => ({
        open:false, search:'', value:config.value, options:config.options || [], disabled:false,
        activeIndex:0, panelStyle:{}, observer:null, validationMessage:'',
        init() {
            this.$nextTick(() => {
                this.refreshNative();
                this.$watch('value', () => this.syncNative());
                this.observer = new MutationObserver(() => this.refreshNative());
                this.observer.observe(this.$refs.native, {subtree:true, childList:true, attributes:true, characterData:true});
            });
        },
        destroy() { this.observer?.disconnect(); },
        key(value) {
            if (typeof value === 'boolean' && !this.options.some(option => option.value === String(value))) return value ? '1' : '0';
            return String(value ?? '');
        },
        refreshNative() {
            const native = this.$refs.native;
            this.disabled = native.disabled;
            this.options = [...native.options].map(o => ({value:o.value, label:o.text, disabled:o.disabled || o.parentElement?.disabled}));
            if (!config.bound && (this.value === undefined || this.value === null)) this.value = native.value;
            this.syncNative();
            if (this.open) this.place();
        },
        syncNative() {
            const native = this.$refs.native;
            if (native && native.value !== this.key(this.value)) native.value = this.key(this.value);
        },
        get filteredOptions() {
            const term = this.search.trim().toLowerCase();
            return this.options.filter(o => !term || o.label.toLowerCase().includes(term) || o.value.toLowerCase().includes(term));
        },
        get selectedLabel() { return this.options.find(o => o.value === this.key(this.value))?.label ?? config.placeholder; },
        get accessibleLabel() {
            const native = this.$refs.native;
            return native?.getAttribute('aria-label') || [...(native?.labels || [])].map(label => {
                const copy = label.cloneNode(true);
                copy.querySelectorAll('select,button,input,textarea,template').forEach(control => control.remove());
                return copy.textContent.trim();
            }).join(' ') || config.placeholder;
        },
        place() { this.panelStyle = ui.position(this.$refs.trigger, this.$refs.panel, 240, config.align); },
        show() {
            if (this.disabled) return;
            this.open = true; this.search = '';
            this.activeIndex = Math.max(0, this.options.findIndex(o => o.value === this.key(this.value)));
            this.$nextTick(() => { this.place(); (this.$refs.searchInput || this.$refs.panel).focus(); this.revealActive(); });
        },
        close(focus = false) { this.open=false; this.search=''; if (focus) this.$refs.trigger.focus(); },
        select(option) {
            if (!option || option.disabled) return;
            this.value = typeof this.value === 'boolean' ? ['1','true'].includes(option.value) : option.value;
            this.validationMessage=''; this.syncNative();
            this.$refs.native.dispatchEvent(new Event('change', {bubbles:true}));
            this.close(true);
        },
        move(step) {
            const options = this.filteredOptions;
            if (!options.some(o => !o.disabled)) return;
            do { this.activeIndex = (this.activeIndex + step + options.length) % options.length; } while (options[this.activeIndex].disabled);
            this.revealActive();
        },
        revealActive() { this.$nextTick(() => this.$refs.panel.querySelectorAll('[role=option]')[this.activeIndex]?.scrollIntoView({block:'nearest'})); },
        invalid() { this.validationMessage = this.$refs.native.validationMessage; this.$refs.trigger.focus(); this.show(); },
    });
    ui.syncTheme();
    document.addEventListener('livewire:navigating', () => { locks.clear(); document.documentElement.classList.remove('lp-overlay-open'); });
    document.addEventListener('livewire:navigated', () => ui.syncTheme());
})();
