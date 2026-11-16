export function installRealtimeAccess(pusher, notify = () => {}) {
    const denied = new Map();
    const authorize = pusher.config.channelAuthorizer;
    let notice = null;
    const render = () => notify(notice, denied.size > 0);
    const access = {
        get blocked() { return denied.size > 0; },
        get notice() { return notice; },
        reject(status, channel = null, error = null) {
            notice = [401, 419].includes(status) ? 'expired' : status === 403 ? 'revoked' : 'offline';
            if ([401, 403, 419].includes(status)) denied.set(channel || '*', error);
            render();
        },
        retry() {
            denied.clear(); notice = null; render();
            for (const channel of pusher.allChannels()) {
                if (!channel.name.startsWith('private-')) continue;
                channel.subscribed = false;
                channel.subscriptionPending = false;
                channel.subscribe();
            }
        },
    };
    pusher.config.channelAuthorizer = (parameters, callback) => {
        const cached = denied.get(parameters.channelName) || denied.get('*');
        if (cached) return callback(cached, null);
        authorize(parameters, (error, data) => {
            if (error) access.reject(error.status, parameters.channelName, error);
            callback(error, data);
        });
    };
    pusher.bind('clinicos:access-revoked', () => { if (!denied.size) notice = 'changed'; render(); });
    pusher.bind('clinicos:access-unavailable', () => { if (!denied.size) notice = 'offline'; render(); });
    pusher.bind('clinicos:access-restored', () => { if (!denied.size) notice = null; render(); });
    return access;
}

export function renderRealtimeAccess(notice, blocked) {
    const messages = {
        expired: 'Sesi berakhir. Simpan salinan perubahan dan masuk kembali di tab baru. Draft dan foto di halaman ini tetap ada.',
        revoked: 'Akses realtime telah berubah. Hubungi admin klinik bila akses ini masih diperlukan. Draft dan foto di halaman ini tetap ada.',
        changed: 'Hak atau sesi realtime berubah. Periksa akses lagi. Draft dan foto di halaman ini tetap ada.',
        offline: 'Koneksi realtime terganggu. Data terakhir dan perubahan yang belum disimpan tetap ada.',
    };
    for (const element of document.querySelectorAll('[data-realtime-access-feedback]')) {
        element.hidden = !notice;
        element.querySelector('[data-realtime-access-message]').textContent = messages[notice] || '';
    }
    window.dispatchEvent(new CustomEvent('clinicos:realtime-access', { detail: { notice, blocked } }));
}
