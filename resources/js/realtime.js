const subscriptions = new Set();
let refreshTimer;
let hasConnected = false;
let connectionState = 'reconnecting';

const connectionPresentations = {
    connected: {
        label: 'Terhubung · diperbarui otomatis',
        dotClasses: ['bg-emerald-400'],
    },
    refetching: {
        label: 'Memperbarui data…',
        dotClasses: ['bg-sky-400', 'animate-pulse'],
    },
    reconnecting: {
        label: 'Menghubungkan kembali…',
        dotClasses: ['bg-amber-400', 'animate-pulse'],
    },
    disconnected: {
        label: 'Koneksi terputus · menampilkan data terakhir',
        dotClasses: ['bg-red-400'],
    },
};

function renderConnectionState() {
    const presentation = connectionPresentations[connectionState];

    document.querySelectorAll('[data-realtime-connection-state]').forEach((element) => {
        const label = element.querySelector('[data-realtime-connection-label]');
        const dot = element.querySelector('[data-realtime-connection-dot]');

        element.dataset.realtimeConnectionState = connectionState;

        if (label) {
            label.textContent = presentation.label;
        }

        if (dot) {
            dot.classList.remove('bg-emerald-400', 'bg-sky-400', 'bg-amber-400', 'bg-red-400', 'animate-pulse');
            dot.classList.add(...presentation.dotClasses);
        }
    });
}

function setConnectionState(state) {
    connectionState = state;
    renderConnectionState();
}

function isRealtimeConnected() {
    return window.Echo?.connector?.pusher?.connection?.state === 'connected';
}

async function refreshRealtimeRegion(showRefetching = true) {
    const currentRegion = document.querySelector('[data-realtime-refresh-root]');

    if (! currentRegion) {
        return false;
    }

    try {
        if (showRefetching) {
            setConnectionState('refetching');
        }

        const response = await fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const documentFromServer = new DOMParser().parseFromString(await response.text(), 'text/html');
        const freshRegion = documentFromServer.querySelector('[data-realtime-refresh-root]');

        if (! freshRegion) {
            throw new Error('Realtime refresh region tidak ditemukan.');
        }

        currentRegion.replaceWith(freshRegion);
        setConnectionState(isRealtimeConnected() ? 'connected' : 'disconnected');

        return true;
    } catch (error) {
        setConnectionState('disconnected');
        console.warn('ClinicOS realtime refresh gagal.', error);

        return false;
    }
}

function showQueueActionFeedback(message, type = 'error') {
    const feedback = document.querySelector('[data-queue-action-feedback]');

    if (! feedback) {
        return;
    }

    feedback.classList.remove(
        'hidden',
        'border-clinic-200',
        'bg-clinic-50',
        'text-clinic-800',
        'border-red-200',
        'bg-red-50',
        'text-red-700',
    );
    feedback.classList.add(...(type === 'success'
        ? ['border-clinic-200', 'bg-clinic-50', 'text-clinic-800']
        : ['border-red-200', 'bg-red-50', 'text-red-700']));
    feedback.textContent = message;
}

function setQueueActionPending(form, isPending) {
    form.dataset.submitting = String(isPending);
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = isPending;

        if (! button.dataset.defaultLabel) {
            button.dataset.defaultLabel = button.textContent.trim();
        }

        button.textContent = isPending ? 'Memproses…' : button.dataset.defaultLabel;
    });
}

function validationMessage(payload) {
    const messages = Object.values(payload.errors ?? {}).flat();

    return messages[0] ?? payload.message ?? 'Data aksi tidak valid.';
}

async function handleQueueActionSubmit(event) {
    const form = event.target.closest?.('form[data-queue-action]');

    if (! form || event.defaultPrevented) {
        return;
    }

    event.preventDefault();

    if (form.dataset.submitting === 'true') {
        return;
    }

    setQueueActionPending(form, true);

    try {
        const response = await fetch(form.action, {
            method: form.method,
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));

        if (! response.ok) {
            const message = response.status === 403
                ? 'Anda tidak memiliki izin untuk aksi ini.'
                : response.status === 422
                    ? validationMessage(payload)
                    : 'Aksi belum dapat dipastikan. Muat ulang data sebelum mencoba lagi.';

            showQueueActionFeedback(message);
            setQueueActionPending(form, false);

            return;
        }

        if (payload.redirect) {
            window.location.assign(payload.redirect);

            return;
        }

        if (await refreshRealtimeRegion(false)) {
            showQueueActionFeedback(payload.message ?? 'Status antrean berhasil diperbarui.', 'success');
        } else {
            showQueueActionFeedback('Aksi berhasil, tetapi data terbaru belum dapat dimuat. Muat ulang halaman.');
        }
    } catch (error) {
        showQueueActionFeedback('Aksi belum dapat dipastikan. Muat ulang data sebelum mencoba lagi.');
        setQueueActionPending(form, false);
        console.warn('ClinicOS queue action gagal.', error);
    }
}

function scheduleRefresh(showRefetching = true) {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => refreshRealtimeRegion(showRefetching), 250);
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
        channel.listen('.queue.updated', () => scheduleRefresh());
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
        setConnectionState('disconnected');
        return;
    }

    setConnectionState(isRealtimeConnected() ? 'connected' : 'reconnecting');
    subscribeQueues();
    subscribeNotifications();
}

document.addEventListener('DOMContentLoaded', initializeRealtime);
document.addEventListener('livewire:navigated', initializeRealtime);
document.addEventListener('submit', handleQueueActionSubmit);

const echoConnection = window.Echo?.connector?.pusher?.connection;

echoConnection?.bind('connecting', () => setConnectionState('reconnecting'));
echoConnection?.bind('unavailable', () => setConnectionState('disconnected'));
echoConnection?.bind('failed', () => setConnectionState('disconnected'));
echoConnection?.bind('disconnected', () => setConnectionState('disconnected'));
echoConnection?.bind('error', () => setConnectionState('disconnected'));
echoConnection?.bind('connected', () => {
    if (hasConnected) {
        scheduleRefresh();
    } else {
        setConnectionState('connected');
    }

    hasConnected = true;
});

// shortcut: polling backs up WebSockets for tunnel testing; expose Reverb publicly before scaling traffic.
window.setInterval(() => scheduleRefresh(false), 5000);
