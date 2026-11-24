/**
 * Lead Panther CRM - Progressive Web App (PWA) Client Controller
 * Handles beforeinstallprompt, iOS Add-to-Home-Screen instructions, and standalone state detection.
 */
(function () {
    'use strict';

    window.__pwaInstallPrompt = null;

    const LeadPantherPWA = {
        isIOS() {
            return (
                (/iPad|iPhone|iPod/.test(navigator.userAgent) ||
                    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) &&
                !window.MSStream
            );
        },

        isStandalone() {
            return (
                window.matchMedia('(display-mode: standalone)').matches ||
                window.matchMedia('(display-mode: fullscreen)').matches ||
                window.navigator.standalone === true ||
                document.referrer.includes('android-app://') ||
                localStorage.getItem('leadpanther_pwa_installed') === 'true'
            );
        },

        isInstalled() {
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                return true;
            }
            return localStorage.getItem('leadpanther_pwa_installed') === 'true';
        },

        openModal(tab = null) {
            if (tab) {
                window.dispatchEvent(new CustomEvent('pwa-select-tab', { detail: tab }));
            }
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'pwa-install-modal' }));
        },

        async promptAndroid() {
            if (window.__pwaInstallPrompt) {
                try {
                    window.__pwaInstallPrompt.prompt();
                    const choice = await window.__pwaInstallPrompt.userChoice;
                    if (choice && choice.outcome === 'accepted') {
                        localStorage.setItem('leadpanther_pwa_installed', 'true');
                        this.updateUI();
                        this.showToast('Installing Lead Panther...', 'success');
                    }
                    window.__pwaInstallPrompt = null;
                } catch (err) {
                    console.warn('PWA install prompt error:', err);
                }
            }
        },

        async install() {
            const installed = this.isInstalled();
            if (installed) {
                this.openModal();
                return;
            }

            // iOS Safari: Open project modal with iOS instructions
            if (this.isIOS()) {
                this.openModal('ios');
                return;
            }

            // Android / Chromium: if native prompt is available, trigger browser installation prompt
            if (window.__pwaInstallPrompt) {
                try {
                    window.__pwaInstallPrompt.prompt();
                    const choice = await window.__pwaInstallPrompt.userChoice;
                    if (choice && choice.outcome === 'accepted') {
                        localStorage.setItem('leadpanther_pwa_installed', 'true');
                        this.updateUI();
                        this.showToast('Installing Lead Panther...', 'success');
                    }
                    window.__pwaInstallPrompt = null;
                } catch (err) {
                    console.warn('PWA install error:', err);
                    this.openModal('android');
                }
                return;
            }

            // Desktop or prompt not captured yet: open modal with clear guidance
            const defaultTab = this.isIOS() ? 'ios' : (/Android/i.test(navigator.userAgent) ? 'android' : 'desktop');
            this.openModal(defaultTab);
        },

        init() {
            // Register service worker if supported
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/browser-push-sw.js', { scope: '/' })
                        .catch(err => console.debug('PWA Service Worker registration:', err));
                });
            }

            // Capture Android / Chromium install prompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                window.__pwaInstallPrompt = e;
                this.updateUI();
                window.dispatchEvent(new CustomEvent('pwa-prompt-available'));
            });

            // App installed event
            window.addEventListener('appinstalled', () => {
                window.__pwaInstallPrompt = null;
                localStorage.setItem('leadpanther_pwa_installed', 'true');
                this.updateUI();
                window.dispatchEvent(new CustomEvent('pwa-installed'));
                this.showToast('Lead Panther installed on your Home Screen!', 'success');
            });

            // Update UI on initial load and navigation
            this.updateUI();
            document.addEventListener('livewire:navigated', () => this.updateUI());
            window.addEventListener('popstate', () => this.updateUI());
        },

        updateUI() {
            const installed = this.isInstalled();
            window.isLeadPantherPWAInstalled = installed;

            // Update all install buttons
            document.querySelectorAll('[data-pwa-install-button]').forEach((btn) => {
                const label = btn.querySelector('[data-pwa-install-label]');
                const iconInstall = btn.querySelector('[data-pwa-icon-install]');
                const iconInstalled = btn.querySelector('[data-pwa-icon-installed]');
                const badge = btn.querySelector('[data-pwa-badge]');
                const badgeNew = btn.querySelector('[data-pwa-badge-new]');

                if (installed) {
                    btn.setAttribute('data-installed', 'true');
                    btn.setAttribute('title', 'Lead Panther is installed');
                    if (label) label.textContent = 'App Installed';
                    if (iconInstall) iconInstall.classList.add('hidden');
                    if (iconInstalled) iconInstalled.classList.remove('hidden');
                    if (badge) badge.classList.add('hidden');
                    if (badgeNew) badgeNew.classList.add('hidden');
                } else {
                    btn.setAttribute('data-installed', 'false');
                    btn.setAttribute('title', 'Install Lead Panther on your device');
                    if (label) label.textContent = 'Install Lead Panther';
                    if (iconInstall) iconInstall.classList.remove('hidden');
                    if (iconInstalled) iconInstalled.classList.add('hidden');
                    if (badge) badge.classList.add('hidden');
                    if (badgeNew) badgeNew.classList.remove('hidden');
                }
            });
        },

        showToast(message, type = 'info') {
            if (window.LeadPantherUI && typeof window.LeadPantherUI.toast === 'function') {
                window.LeadPantherUI.toast(message, type);
            } else if (window.$dispatch) {
                window.$dispatch('show-toast', { message, type });
            }
        }
    };

    window.LeadPantherPWA = LeadPantherPWA;
    window.triggerLeadPantherInstall = () => LeadPantherPWA.install();
    window.openLeadPantherInstallModal = (tab) => LeadPantherPWA.openModal(tab);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => LeadPantherPWA.init());
    } else {
        LeadPantherPWA.init();
    }
})();
