export class RmeAutosave {
    constructor(form, { request = (...args) => fetch(...args), delay = 1200, navigate = (url) => window.location.assign(url) } = {}) {
        this.form = form;
        this.request = request;
        this.delay = delay;
        this.navigate = navigate;
        this.enabled = form.hasAttribute('data-rme-autosave');
        this.dirty = false;
        this.blocked = false;
        this.sessionExpired = false;
        this.submitting = false;
        this.inFlight = null;
        this.timer = null;
        this.confirmed = false;
        this.change = () => this.markDirty();
        form.addEventListener('input', this.change);
        form.addEventListener('change', this.change);
        form.addEventListener('rme-changed', this.change);
        form.addEventListener('dental-condition-changed', this.change);
        form.addEventListener('submit', (event) => this.submit(event), true);
        form.addEventListener('rme-retry', () => this.enabled ? this.flush() : this.form.requestSubmit());
    }

    state(state, message = null) {
        this.form.dispatchEvent(new CustomEvent('rme-autosave-state', { bubbles: true, detail: { state, message } }));
    }

    showErrors(errors = {}, focus = false) {
        for (const message of this.form.querySelectorAll('[data-rme-network-error]')) message.remove();
        for (const field of this.form.querySelectorAll('[data-rme-network-invalid]')) {
            field.removeAttribute('aria-invalid');
            field.setAttribute('aria-describedby', field.dataset.previousDescription || '');
            delete field.dataset.rmeNetworkInvalid;
        }
        for (const [key, messages] of Object.entries(errors)) {
            const name = key.replace(/\.([^.]+)/g, '[$1]');
            const field = [...this.form.elements].find(element => element.name === name);
            if (! field || field.type === 'hidden') continue;
            const message = document.createElement('p');
            message.id = `rme-error-${key.replaceAll('.', '-')}`;
            message.dataset.rmeNetworkError = '';
            message.className = 'mt-2 text-sm text-red-700';
            message.textContent = messages[0];
            field.dataset.previousDescription = field.getAttribute('aria-describedby') || '';
            field.dataset.rmeNetworkInvalid = '';
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', `${field.dataset.previousDescription} ${message.id}`.trim());
            field.after(message);
        }
        this.form.dispatchEvent(new CustomEvent('rme-validation-errors', { detail: { errors, focus } }));
    }

    markDirty() {
        if (this.submitting) return;
        this.dirty = true;
        clearTimeout(this.timer);
        if (this.blocked) return;
        this.state(this.enabled ? 'draft' : 'amending');
        if (this.enabled) this.timer = setTimeout(() => this.flush(), this.delay);
    }

    hasPendingPhotos() {
        return Boolean(this.form.querySelector('input[type="file"]')?.files?.length
            || this.form.querySelector('[name="removed_photos[]"]'));
    }

    async flush() {
        clearTimeout(this.timer);
        while (this.inFlight) await this.inFlight;
        if (! this.dirty || this.blocked || this.submitting || ! this.form.isConnected || ! this.enabled) return ! this.blocked;
        this.dirty = false;
        this.state('saving');
        const data = new FormData(this.form);
        // shortcut: foto lewat submit manual; gunakan upload idempoten jika autosave foto diperlukan.
        for (const name of [...data.keys()]) {
            if (name === 'photos[]' || name.startsWith('removed_photos[')) data.delete(name);
        }
        this.inFlight = (async () => {
            try {
                const response = await this.request(this.form.action, {
                    method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: AbortSignal.timeout(15000),
                });
                const payload = await response.json().catch(() => ({}));
                this.showErrors(payload.errors);
                if (! response.ok) {
                    this.sessionExpired = [401, 419].includes(response.status);
                    this.blocked = [401, 403, 409, 419].includes(response.status);
                    throw new Error(response.status === 409 ? payload.message
                        : this.sessionExpired ? 'Sesi berakhir. Salin perubahan Anda lalu masuk kembali di tab baru. Setelah itu muat ulang editor.'
                        : Object.values(payload.errors ?? {}).flat()[0] ?? 'Penyimpanan gagal. Periksa koneksi lalu coba lagi.');
                }
                if (! Number.isInteger(payload.record_version)) throw new Error('Konfirmasi penyimpanan tidak valid. Coba lagi.');
                this.form.elements.record_version.value = payload.record_version;
                this.confirmed = true;
                this.state(this.dirty ? 'draft' : this.hasPendingPhotos() ? 'photos' : 'saved');
                return true;
            } catch (error) {
                this.dirty = true;
                this.state(this.sessionExpired ? 'expired' : this.blocked ? 'conflict' : 'error', error.message);
                return false;
            }
        })();
        const saved = await this.inFlight;
        this.inFlight = null;
        if (saved && this.dirty) this.timer = setTimeout(() => this.flush(), this.delay);
        return saved;
    }

    async submit(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        clearTimeout(this.timer);
        if (this.submitting || this.blocked) return;
        this.submitting = true;
        this.form.inert = true;
        while (this.inFlight) await this.inFlight;
        if (this.blocked) {
            this.submitting = false;
            this.form.inert = false;
            return;
        }
        const action = event.submitter?.getAttribute('formaction') || this.form.action;
        if (this.enabled && action === this.form.action && this.confirmed && ! this.dirty && ! this.hasPendingPhotos()) {
            this.submitting = false;
            this.form.inert = false;
            this.state('saved');
            return;
        }
        this.state('saving');
        try {
            const response = await this.request(action, {
                method: 'POST', body: new FormData(this.form), credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: AbortSignal.timeout(30000),
            });
            const payload = await response.json().catch(() => ({}));
            this.showErrors(payload.errors, true);
            if (! response.ok) {
                this.sessionExpired = [401, 419].includes(response.status);
                this.blocked = [401, 403, 409, 419].includes(response.status);
                throw new Error(response.status === 409 ? payload.message
                    : this.sessionExpired ? 'Sesi berakhir. Salin perubahan Anda lalu masuk kembali di tab baru. Setelah itu muat ulang editor.'
                    : response.status === 422 ? Object.values(payload.errors ?? {}).flat()[0]
                    : 'Data belum tersimpan. Periksa koneksi lalu coba lagi.');
            }
            if (typeof payload.redirect !== 'string' || ! payload.redirect) throw new Error('Konfirmasi penyimpanan tidak valid. Pertahankan isian dan coba lagi.');
            this.dirty = false;
            this.navigate(payload.redirect || window.location.href);
        } catch (error) {
            this.state(this.sessionExpired ? 'expired' : this.blocked ? 'conflict' : 'error', error.message);
            this.form.inert = false;
            this.submitting = false;
        }
    }

    get unsaved() {
        return ! this.submitting && (this.dirty || Boolean(this.inFlight) || this.hasPendingPhotos());
    }

    destroy() {
        clearTimeout(this.timer);
    }
}

if (typeof document !== 'undefined') {
    let client;
    const initialize = () => {
        const form = document.querySelector('[data-rme-form]');
        if (client?.form === form) return;
        client?.destroy();
        client = form ? new RmeAutosave(form) : null;
        if (form) form.rmeAutosave = client;
    };
    document.addEventListener('DOMContentLoaded', initialize);
    document.addEventListener('livewire:navigated', initialize);
    window.addEventListener('online', () => client?.flush());
    window.addEventListener('beforeunload', (event) => {
        if (client?.unsaved) { event.preventDefault(); event.returnValue = ''; }
    });
    document.addEventListener('livewire:navigate', (event) => {
        if (client?.unsaved && ! window.confirm('Ada perubahan atau foto yang belum tersimpan. Tinggalkan halaman?')) event.preventDefault();
    });
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-care-form]');
        if (! form) return;
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;
        const feedback = form.querySelector('[data-care-error]');
        const button = event.submitter;
        feedback.textContent = '';
        form.dataset.submitting = 'true';
        if (button) button.disabled = true;
        try {
            if (client?.hasPendingPhotos()) throw new Error('Simpan foto melalui tombol Simpan draft sebelum mengubah alur pelayanan.');
            if (client && ! await client.flush()) throw new Error('Simpan perubahan RME sebelum mengubah alur pelayanan.');
            if (client?.form.elements.record_version && form.elements.record_version) form.elements.record_version.value = client.form.elements.record_version.value;
            if (client) client.form.inert = true;
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(30000) });
            const payload = await response.json().catch(() => ({}));
            if (! response.ok) {
                if (response.status === 409) form.querySelector('[data-care-review]')?.classList.remove('hidden');
                throw new Error(Object.values(payload.errors ?? {}).flat()[0] ?? payload.message ?? 'Perubahan belum dikonfirmasi. Pertahankan isian dan coba lagi.');
            }
            if (client) { client.dirty = false; client.submitting = true; }
            window.location.assign(payload.redirect || window.location.href);
        } catch (error) {
            feedback.textContent = error.message;
            if (client) client.form.inert = false;
            if (button) button.disabled = false;
            form.dataset.submitting = 'false';
        }
    });
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-allergy-form]');
        if (! form) return;
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;
        const feedback = form.querySelector('[role="alert"]');
        feedback.textContent = '';
        if (client?.hasPendingPhotos()) {
            feedback.textContent = 'Simpan foto melalui tombol Simpan draft sebelum memperbarui alergi.';
            return;
        }
        form.dataset.submitting = 'true';
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            if (client && ! await client.flush()) throw new Error('Simpan perubahan RME terlebih dahulu sebelum memperbarui alergi.');
            if (client) client.form.inert = true;
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(15000),
            });
            const payload = await response.json();
            if (! response.ok) {
                if (response.status === 409) form.querySelector('[data-allergy-review]')?.classList.remove('hidden');
                throw new Error(Object.values(payload.errors ?? {}).flat()[0] ?? payload.message ?? 'Data alergi belum tersimpan. Coba lagi.');
            }
            window.location.reload();
        } catch (error) {
            feedback.textContent = error.message;
            if (client) client.form.inert = false;
            button.disabled = false;
            form.dataset.submitting = 'false';
        }
    });
}
