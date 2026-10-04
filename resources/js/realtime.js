const subscriptions = new Set();
let refreshTimer;
let hasConnected = false;

async function refreshRealtimeRegion() {
    const currentRegion = document.querySelector('[data-realtime-refresh-root]');

    if (! currentRegion) {
        return;
    }

    try {
        const response = await fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const documentFromServer = new DOMParser().parseFromString(await response.text(), 'text/html');
        const freshRegion = documentFromServer.querySelector('[data-realtime-refresh-root]');

        if (freshRegion) {
            currentRegion.replaceWith(freshRegion);
        }
    } catch (error) {
        console.warn('ClinicOS realtime refresh gagal.', error);
    }
}

function scheduleRefresh() {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(refreshRealtimeRegion, 250);
}

function subscribeQueues() {
    document.querySelectorAll('[data-realtime-queue-channel]').forEach((element) => {
        const channelName = element.dataset.realtimeQueueChannel;
        const isPublic = element.hasAttribute('data-realtime-public');
        const key = `${isPublic ? 'public' : 'private'}:${channelName}`;

        if (! channelName || subscriptions.has(key)) {
            return;
        }

        const channel = isPublic ? window.Echo.channel(channelName) : window.Echo.private(channelName);
        channel.listen('.queue.updated', scheduleRefresh);
        subscriptions.add(key);
    });
}

function subscribeNotifications() {
    const element = document.querySelector('[data-user-notification-channel]');
    const channelName = element?.dataset.userNotificationChannel;
    const key = `notification:${channelName}`;

    if (! channelName || subscriptions.has(key)) {
        return;
    }

    window.Echo.private(channelName).notification((notification) => {
        const counter = document.querySelector('[data-notification-count]');

        if (counter) {
            counter.textContent = String(Number(counter.textContent || 0) + 1);
            counter.classList.remove('hidden');
        }

        window.dispatchEvent(new CustomEvent('clinicos:notification', { detail: notification }));
    });
    subscriptions.add(key);
}

function initializeRealtime() {
    if (! window.Echo) {
        return;
    }

    subscribeQueues();
    subscribeNotifications();
}

document.addEventListener('DOMContentLoaded', initializeRealtime);
document.addEventListener('livewire:navigated', initializeRealtime);

window.Echo?.connector?.pusher?.connection?.bind('connected', () => {
    if (hasConnected) {
        scheduleRefresh();
    }

    hasConnected = true;
});
