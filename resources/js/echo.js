import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
import { installRealtimeAccess, renderRealtimeAccess } from './realtime-access';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

window.ClinicOSRealtimeAccess = installRealtimeAccess(window.Echo.connector.pusher, renderRealtimeAccess);
for (const event of ['DOMContentLoaded', 'livewire:navigated']) {
    document.addEventListener(event, () => renderRealtimeAccess(window.ClinicOSRealtimeAccess.notice, window.ClinicOSRealtimeAccess.blocked));
}
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-realtime-access-retry]')) window.ClinicOSRealtimeAccess.retry();
});
