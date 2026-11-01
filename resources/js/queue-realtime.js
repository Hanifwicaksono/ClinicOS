/**
 * ClinicOS Queue Realtime & Browser Recovery Module
 *
 * Mengelola langganan channel Echo, pemulihan koneksi, resync snapshot,
 * deteksi event hilang, fallback polling, dan teardown listener/timer.
 */

export class QueueRealtimeClient {
    /**
     * @param {Object} options
     * @param {string} [options.channelName] Nama channel Echo (e.g. 'clinic.1.queues' atau 'clinic.1.queue-board')
     * @param {boolean} [options.isPublic=false] Apakah channel publik atau private
     * @param {Function} [options.onUpdate] Callback saat event queue.updated diterima
     * @param {Function} [options.onStateChange] Callback saat state koneksi berubah
     * @param {Function} [options.onSnapshotRequest] Callback untuk memicu snapshot refresh
     * @param {number} [options.pollingIntervalMs=10000] Interval fallback polling (~10 detik)
     * @param {number} [options.baseBackoffMs=1000] Base backoff untuk reconnect
     * @param {number} [options.maxBackoffMs=30000] Max backoff untuk reconnect
     */
    constructor(options = {}) {
        this.channelName = options.channelName || null;
        this.isPublic = options.isPublic ?? false;
        this.onUpdate = options.onUpdate || null;
        this.onStateChange = options.onStateChange || null;
        this.onSnapshotRequest = options.onSnapshotRequest || null;
        this.pollingIntervalMs = options.pollingIntervalMs ?? 10000;
        this.baseBackoffMs = options.baseBackoffMs ?? 1000;
        this.maxBackoffMs = options.maxBackoffMs ?? 30000;

        /** @type {'connected' | 'reconnecting' | 'offline' | 'refetching'} */
        this.state = 'reconnecting';
        this.hasConnectedOnce = false;
        this.reconnectAttempts = 0;
        this.lastSequence = null;
        this.lastEventTime = null;
        this.isDestroyed = false;

        this.pollingTimer = null;
        this.reconnectTimer = null;
        this.debounceTimer = null;

        this.boundVisibilityHandler = this.handleVisibilityChange.bind(this);
        this.boundOnlineHandler = this.handleOnline.bind(this);
        this.boundOfflineHandler = this.handleOffline.bind(this);
        this.boundUnloadHandler = this.destroy.bind(this);

        this.init();
    }

    init() {
        this.setupDomEvents();
        this.setupEcho();
    }

    setupDomEvents() {
        document.addEventListener('visibilitychange', this.boundVisibilityHandler);
        window.addEventListener('online', this.boundOnlineHandler);
        window.addEventListener('offline', this.boundOfflineHandler);
        window.addEventListener('pagehide', this.boundUnloadHandler);
        window.addEventListener('beforeunload', this.boundUnloadHandler);
    }

    setupEcho() {
        if (! window.Echo) {
            this.setState('offline');
            this.startFallbackPolling();
            return;
        }

        const pusherConnection = window.Echo.connector?.pusher?.connection;

        if (pusherConnection) {
            pusherConnection.bind('connecting', () => this.handleConnecting());
            pusherConnection.bind('connected', () => this.handleConnected());
            pusherConnection.bind('unavailable', () => this.handleUnavailable());
            pusherConnection.bind('failed', () => this.handleFailed());
            pusherConnection.bind('disconnected', () => this.handleDisconnected());
            pusherConnection.bind('error', (err) => this.handleError(err));

            if (pusherConnection.state === 'connected') {
                this.handleConnected();
            } else {
                this.setState('reconnecting');
            }
        } else {
            this.setState('reconnecting');
        }

        this.subscribe();
    }

    subscribe() {
        if (! this.channelName || ! window.Echo || this.isDestroyed) {
            return;
        }

        try {
            const channel = this.isPublic
                ? window.Echo.channel(this.channelName)
                : window.Echo.private(this.channelName);

            channel.listen('.queue.updated', (payload) => this.handleQueueEvent(payload));
        } catch (error) {
            console.warn(`[ClinicOS Realtime] Gagal subscribe ke channel ${this.channelName}:`, error);
        }
    }

    handleQueueEvent(payload) {
        if (this.isDestroyed) return;

        // Deteksi event hilang (sequence loncat / out-of-order)
        // TODO(Dhomi): Backend payload saat ini belum menyediakan counter integer 'sequence'.
        // Jika Dhomi menambahkan 'sequence' atau 'version' pada event, gunakan deteksi loncatan:
        let gapDetected = false;
        if (typeof payload.sequence === 'number') {
            if (this.lastSequence !== null && payload.sequence > this.lastSequence + 1) {
                gapDetected = true;
                console.warn(
                    `[ClinicOS Realtime] Sequence gap terdeteksi: expected ${this.lastSequence + 1}, got ${payload.sequence}. Memicu resync snapshot.`
                );
            }
            this.lastSequence = payload.sequence;
        } else if (payload.updated_at && this.lastEventTime) {
            const eventTimestamp = new Date(payload.updated_at).getTime();
            if (eventTimestamp < this.lastEventTime) {
                gapDetected = true;
                console.warn('[ClinicOS Realtime] Event out-of-order terdeteksi berdasarkan timestamp. Memicu resync snapshot.');
            }
            this.lastEventTime = Math.max(this.lastEventTime, eventTimestamp);
        } else if (payload.updated_at) {
            this.lastEventTime = new Date(payload.updated_at).getTime();
        }

        // Emit CustomEvent untuk komponen-komponen UI
        window.dispatchEvent(new CustomEvent('clinicos:queue-updated', { detail: payload }));

        if (typeof this.onUpdate === 'function') {
            this.onUpdate(payload);
        }

        // Jika ada gap event, langsung resync snapshot. Jika urutan normal, refresh dengan debounce.
        if (gapDetected) {
            this.requestSnapshot();
        } else {
            this.scheduleSnapshotRefresh();
        }
    }

    handleConnecting() {
        this.setState('reconnecting');
    }

    handleConnected() {
        this.reconnectAttempts = 0;
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }

        // Hentikan fallback polling saat WebSocket pulih
        this.stopFallbackPolling();

        this.setState('connected');

        // Resync snapshot saat reconnect berhasil (bila sebelumnya sudah pernah terkoneksi)
        if (this.hasConnectedOnce) {
            this.requestSnapshot();
        }
        this.hasConnectedOnce = true;
    }

    handleUnavailable() {
        this.setState('offline');
        this.startFallbackPolling();
        this.scheduleExponentialReconnect();
    }

    handleFailed() {
        this.setState('offline');
        this.startFallbackPolling();
        this.scheduleExponentialReconnect();
    }

    handleDisconnected() {
        this.setState('reconnecting');
        this.startFallbackPolling();
        this.scheduleExponentialReconnect();
    }

    handleError(err) {
        console.warn('[ClinicOS Realtime] Connection error:', err);
        this.setState('offline');
        this.startFallbackPolling();
        this.scheduleExponentialReconnect();
    }

    scheduleExponentialReconnect() {
        if (this.reconnectTimer || this.isDestroyed) {
            return;
        }

        this.reconnectAttempts++;
        const backoff = Math.min(
            this.maxBackoffMs,
            this.baseBackoffMs * Math.pow(1.5, this.reconnectAttempts)
        );
        // Jitter: +/- 25% variasi acak untuk menghindari thundering herd
        const jitter = backoff * (0.75 + Math.random() * 0.5);

        this.reconnectTimer = setTimeout(() => {
            this.reconnectTimer = null;
            if (this.isDestroyed) return;

            const connection = window.Echo?.connector?.pusher?.connection;
            if (connection && connection.state !== 'connected') {
                try {
                    connection.connect();
                } catch (e) {
                    console.warn('[ClinicOS Realtime] Reconnect invocation error:', e);
                }
            }
        }, jitter);
    }

    handleVisibilityChange() {
        if (document.visibilityState === 'visible') {
            // Tab aktif kembali: pastikan WebSocket hidup dan resync snapshot
            const connection = window.Echo?.connector?.pusher?.connection;
            if (connection && connection.state !== 'connected') {
                try {
                    connection.connect();
                } catch (e) {
                    // Ignore
                }
            }
            this.requestSnapshot();
        }
    }

    handleOnline() {
        this.setState('reconnecting');
        const connection = window.Echo?.connector?.pusher?.connection;
        if (connection && connection.state !== 'connected') {
            try {
                connection.connect();
            } catch (e) {
                // Ignore
            }
        }
        this.requestSnapshot();
    }

    handleOffline() {
        this.setState('offline');
        this.startFallbackPolling();
    }

    startFallbackPolling() {
        if (this.pollingTimer || this.isDestroyed) {
            return;
        }

        // Fallback polling (~10 detik) saat WebSocket gagal
        this.pollingTimer = setInterval(() => {
            if (this.isDestroyed) return;

            // Jika websocket sudah 'connected', matikan fallback polling
            const connectionState = window.Echo?.connector?.pusher?.connection?.state;
            if (connectionState === 'connected') {
                this.stopFallbackPolling();
                return;
            }

            this.requestSnapshot(false);
        }, this.pollingIntervalMs);
    }

    stopFallbackPolling() {
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
    }

    scheduleSnapshotRefresh(showRefetching = true) {
        window.clearTimeout(this.debounceTimer);
        this.debounceTimer = window.setTimeout(() => {
            this.requestSnapshot(showRefetching);
        }, 250);
    }

    requestSnapshot(showRefetching = true) {
        if (this.isDestroyed) return;

        if (typeof this.onSnapshotRequest === 'function') {
            this.onSnapshotRequest(showRefetching);
        }
    }

    setState(newState) {
        if (this.state === newState && newState !== 'refetching') {
            return;
        }

        this.state = newState;

        window.dispatchEvent(new CustomEvent('clinicos:queue-connection-state', {
            detail: { state: newState }
        }));

        if (typeof this.onStateChange === 'function') {
            this.onStateChange(newState);
        }
    }

    getState() {
        return this.state;
    }

    destroy() {
        if (this.isDestroyed) return;
        this.isDestroyed = true;

        this.stopFallbackPolling();

        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = null;
        }

        document.removeEventListener('visibilitychange', this.boundVisibilityHandler);
        window.removeEventListener('online', this.boundOnlineHandler);
        window.removeEventListener('offline', this.boundOfflineHandler);
        window.removeEventListener('pagehide', this.boundUnloadHandler);
        window.removeEventListener('beforeunload', this.boundUnloadHandler);

        if (this.channelName && window.Echo) {
            try {
                if (this.isPublic) {
                    window.Echo.leaveChannel(this.channelName);
                } else {
                    window.Echo.leave(this.channelName);
                }
            } catch (e) {
                // Ignore channel leave error during page teardown
            }
        }
    }
}

