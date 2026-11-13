import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'reverb', key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

window.notificationInbox = (userId) => ({
    channel: `App.Models.User.${userId}`,
    init() {
        if (window.Echo) {
            this.subscription = window.Echo.private(this.channel)
                .listen('.notifications.changed', () => window.Livewire?.dispatch('notifications-refresh'));
        }
    },
    destroy() { if (window.Echo) window.Echo.leave(this.channel); },
});
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
