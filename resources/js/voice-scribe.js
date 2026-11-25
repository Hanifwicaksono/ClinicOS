const labels = {
    idle: 'Siap', awaiting_consent: 'Menunggu persetujuan', recording: 'Merekam',
    paused: 'Dijeda', uploading: 'Mengunggah', processing: 'Memproses transkrip dan SOAP',
    review_required: 'Perlu review dokter', approved: 'Disetujui', completed: 'Sudah diterapkan ke draft RME',
    failed: 'Pemrosesan gagal', cancelled: 'Dibatalkan',
};

export class VoiceScribe {
    constructor(root) {
        this.root = root;
        this.base = root.dataset.baseUrl.replace(/\/$/, '');
        this.session = null;
        this.state = 'idle';
        this.busy = true;
        this.loginRequired = false;
        this.pending = [];
        this.nextNumber = 1;
        this.wantRecording = false;
        this.finished = false;
        this.discard = false;
        this.uploading = null;
        this.epoch = 0;
        this.recordedMs = 0;
        root.addEventListener('click', event => {
            const button = event.target.closest('[data-voice-action]');
            if (button) this.perform(button.dataset.voiceAction);
            const tab = event.target.closest('[data-voice-tab]');
            if (tab) this.tab(tab.dataset.voiceTab);
        });
        root.addEventListener('keydown', event => {
            const tabs = [...root.querySelectorAll('[data-voice-tab]')];
            const current = tabs.indexOf(event.target);
            if (current < 0 || ! ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
            this.tab(tabs[index].dataset.voiceTab);
            tabs[index].focus();
        });
        root.addEventListener('input', event => {
            if (event.target.matches('[data-voice-select]')) {
                this.find('[data-voice-confirm]').checked = false;
            }
            this.render();
        });
        this.render();
        this.load();
    }

    find(selector) { return this.root.querySelector(selector); }

    async request(path = '', data = null) {
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content };
        if (data !== null && ! (data instanceof FormData)) headers['Content-Type'] = 'application/json';
        const response = await fetch(this.base + path, {
            method: data === null ? 'GET' : 'POST', credentials: 'same-origin', cache: 'no-store',
            headers, body: data === null ? undefined : data instanceof FormData ? data : JSON.stringify(data),
            signal: AbortSignal.timeout(20000),
        });
        const payload = await response.json().catch(() => ({}));
        if (! response.ok) {
            if ([401, 403, 404, 409, 419].includes(response.status)) this.stopLocal();
            if ([401, 419].includes(response.status)) {
                this.loginRequired = true;
                throw new Error('Sesi login berakhir. Masuk kembali di tab baru, lalu tinjau status sesi Voice sebelum mencoba lagi. Isian RME tetap tersedia.');
            }
            if (response.status >= 500) throw new Error('ClinicOS Voice sedang mengalami gangguan. Coba lagi atau gunakan RME manual.');
            throw new Error(Object.values(payload.errors ?? {}).flat()[0] ?? payload.message ?? 'Permintaan belum dikonfirmasi. Pertahankan halaman dan coba lagi.');
        }
        this.loginRequired = false;
        return payload;
    }

    async load() {
        try {
            const response = await this.request();
            if (response.session) this.accept(response.session);
            else { this.state = 'awaiting_consent'; this.render(); }
        } catch (error) { this.error(error); }
        finally { this.busy = false; this.render(); }
    }

    accept(session) {
        if (this.session?.id !== session.id) {
            for (const field of this.root.querySelectorAll('[data-voice-select]')) field.checked = false;
            this.find('[data-voice-confirm]').checked = false;
        }
        this.session = session;
        this.state = session.status;
        if (! session.failure_code) this.find('[data-voice-error]').textContent = '';
        if (session.failure_code) {
            const failures = {
                provider_unavailable: 'Layanan AI belum tersedia. Pemrosesan mencoba ulang secara terbatas; Anda tetap dapat memakai RME manual.',
                voice_credit_balance_exhausted: 'Saldo kredit API OpenAI habis. Hubungi admin klinik untuk menambah kredit, lalu coba lagi. RME manual tetap tersedia.',
                invalid_clinical_output: 'Hasil AI tidak memenuhi validasi atau bukti transkrip. Coba proses ulang dan tinjau; RME belum berubah.',
                access_or_visit_changed: 'Status kunjungan atau hak akses telah berubah. Muat ulang dan gunakan workflow RME yang sesuai.',
                voice_audio_missing: 'Audio sesi tidak lengkap atau sudah dihapus. Batalkan sesi dan rekam ulang jika masih diperlukan.',
                worker_failed: 'Pemrosesan background gagal. Coba Lagi atau gunakan RME manual.',
                worker_stalled: 'Pemrosesan terlalu lama. Coba Lagi setelah worker tersedia atau gunakan RME manual.',
                draft_expired: 'Draft Voice telah dihapus sesuai retensi. Rekam ulang atau gunakan RME manual.',
                session_abandoned: 'Sesi berakhir karena melewati masa penyimpanan audio. Gunakan sesi baru atau RME manual.',
                voice_transcript_too_long: 'Transkrip melebihi batas pemrosesan. Batalkan sesi dan gunakan rekaman yang lebih singkat atau RME manual.',
                processing_limit: 'Batas tiga percobaan pemrosesan sesi tercapai. Gunakan RME manual.',
            };
            this.find('[data-voice-error]').textContent = failures[session.failure_code] ?? 'Pemrosesan belum selesai. Coba Lagi atau gunakan RME manual.';
        }
        this.nextNumber = Math.max(this.nextNumber, ...session.segments.map(segment => segment.segment_no + 1), 1);
        if (session.draft) {
            this.showResults();
        }
        this.render();
        clearTimeout(this.poll);
        if (session.status === 'processing') this.poll = setTimeout(() => this.refresh(), 2500);
    }

    async refresh() {
        try { this.accept(await this.request('/' + this.session.id)); }
        catch (error) { this.error(error); }
    }

    render() {
        this.root.dataset.state = this.state;
        this.root.dataset.recording = String(this.wantRecording);
        this.find('[data-voice-status]').textContent = labels[this.state] ?? this.state;
        const disclosure = this.root.closest('[data-rme-voice-disclosure]');
        const compactStatus = disclosure?.querySelector('[data-rme-voice-status]');
        if (compactStatus) compactStatus.textContent = this.loginRequired ? 'Sesi login berakhir' : labels[this.state] ?? this.state;
        this.find('[data-voice-login]').hidden = ! this.loginRequired;
        if (disclosure && this.find('[data-voice-error]').textContent) disclosure.open = true;
        this.find('[data-voice-microphone]').textContent = this.wantRecording ? '● Mikrofon aktif — percakapan sedang direkam.' : 'Mikrofon tidak aktif.';
        this.find('[data-voice-upload]').textContent = this.pending.length ? this.pending.length + ' segmen belum dikonfirmasi server. Pertahankan halaman ini.' : 'Tidak ada upload tertunda.';
        const state = this.state;
        const changedProvider = this.session?.provider && this.session.provider !== this.root.dataset.provider;
        const simulated = this.session ? this.session.simulation : this.root.dataset.provider === 'fake';
        this.find('[data-voice-simulation]').hidden = ! simulated && ! changedProvider;
        this.find('[data-voice-simulation-label]').textContent = changedProvider ? (simulated ? 'Sesi simulasi lama' : 'Pengaturan Voice berubah') : 'Mode simulasi';
        this.find('[data-voice-simulation-copy]').textContent = changedProvider
            ? (['completed', 'cancelled'].includes(state)
                ? 'Sesi ini dibuat dengan provider sebelumnya. Mulai rekaman baru untuk memakai provider yang aktif; hasil lama tidak berubah otomatis.'
                : 'Hasil ini berasal dari sesi dengan provider sebelumnya. Batalkan melalui Opsi sesi, lalu mulai rekaman baru untuk memakai provider yang aktif.')
            : 'Hasil berupa contoh, bukan transkripsi audio. Gunakan hanya untuk pengujian.';
        const active = ['recording', 'paused', 'uploading'].includes(state);
        const canRecord = this.root.dataset.canRecord === 'true';
        const show = {
            start: canRecord && ['idle', 'awaiting_consent', 'cancelled', 'completed'].includes(state),
            pause: this.wantRecording,
            resume: canRecord && active && ! this.wantRecording && ! this.finished && ! changedProvider,
            stop: active && ! changedProvider,
            cancel: Boolean(this.session) && ! ['cancelled', 'completed'].includes(state),
            withdraw: Boolean(this.session) && this.session.consent_status === 'granted',
            retry: ! changedProvider && (state === 'failed' || this.pending.length > 0 || state === 'processing'),
            manual: true, approve: state === 'review_required' && canRecord && ! changedProvider,
        };
        for (const button of this.root.querySelectorAll('[data-voice-action]')) {
            const action = button.dataset.voiceAction;
            button.hidden = ! show[action];
            button.disabled = this.busy || (action === 'start' && (! this.find('[data-voice-patient]').checked || ! this.find('[data-voice-agreement]').checked))
                || (action === 'approve' && (! this.find('[data-voice-confirm]').checked || ! this.root.querySelector('[data-voice-select]:checked')));
        }
        this.find('[data-voice-consent]').hidden = ! show.start;
        this.find('[data-voice-options]').hidden = ! show.cancel && ! show.withdraw;
        this.find('[data-voice-review]').hidden = ! this.session?.draft?.payload;
        for (const field of this.root.querySelectorAll('[data-voice-select]')) {
            const value = this.session?.draft?.soap?.[field.dataset.voiceSelect];
            field.closest('.voice-choice').dataset.available = String(Boolean(value?.trim()));
            if (! value?.trim()) field.checked = false;
            field.disabled = this.busy || state !== 'review_required' || ! canRecord || changedProvider || ! value?.trim();
        }
        const selected = this.root.querySelectorAll('[data-voice-select]:checked').length;
        this.find('[data-voice-selection-status]').textContent = state === 'completed' ? 'Hasil Voice sudah dimasukkan ke draft RME.'
            : selected ? `${selected} kolom dipilih untuk draft SOAP.` : 'Pilih minimal satu kolom.';
    }

    async perform(action) {
        if (this.busy) return;
        this.busy = true;
        this.find('[data-voice-error]').textContent = '';
        this.render();
        try {
            if (action === 'start') await this.start();
            else if (action === 'pause') {
                await this.stopLocal();
                this.accept(await this.request('/' + this.session.id + '/pause', {}));
            } else if (action === 'resume') {
                await this.beginCapture();
                this.accept(await this.request('/' + this.session.id + '/resume', {}));
            } else if (action === 'stop') await this.finish();
            else if (action === 'retry') {
                if (this.pending.length) await this.finish();
                else if (this.state === 'processing') await this.refresh();
                else this.accept(await this.request('/' + this.session.id + '/retry', {}));
            } else if (['cancel', 'withdraw'].includes(action)) {
                if (! window.confirm(action === 'withdraw' ? 'Cabut persetujuan dan hapus materi Voice sementara? RME yang sudah tersimpan tetap dipertahankan.' : 'Batalkan sesi dan hapus materi Voice sementara?')) return;
                this.discard = true;
                await this.stopLocal();
                await this.uploading?.catch(() => {});
                this.accept(await this.request('/' + this.session.id + '/' + action, {}));
                this.pending = [];
            } else if (action === 'approve') await this.fillSoap();
            else if (action === 'manual') {
                if (this.wantRecording) {
                    await this.stopLocal();
                    this.accept(await this.request('/' + this.session.id + '/pause', {}));
                }
                window.dispatchEvent(new CustomEvent('rme-open-soap'));
                document.querySelector('[data-rme-form] textarea')?.focus();
            }
        } catch (error) {
            await this.stopLocal();
            this.error(error);
        } finally {
            this.busy = false;
            this.render();
        }
    }

    async start() {
        if (! this.find('[data-voice-patient]').checked || ! this.find('[data-voice-agreement]').checked) throw new Error('Pastikan pasien benar dan persetujuan sudah dicatat.');
        this.discard = false;
        this.finished = false;
        this.pending = [];
        this.nextNumber = 1;
        this.recordedMs = 0;
        this.key ??= crypto.randomUUID();
        this.accept(await this.request('', {
            consent: true, patient_confirmed: true, consent_version: this.root.dataset.consentVersion, idempotency_key: this.key,
        }));
        if (this.session.status !== 'recording') throw new Error('Sesi sebelumnya sudah berubah. Muat ulang untuk meninjau status.');
        this.key = null;
        await this.beginCapture();
    }

    async beginCapture() {
        if (this.recordedMs >= Number(this.root.dataset.maxSeconds) * 1000) throw new Error('Batas durasi sesi tercapai. Selesaikan sesi atau gunakan RME manual.');
        if (! window.isSecureContext || ! navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') throw new Error('Perekaman memerlukan browser kompatibel dan HTTPS (atau localhost). Gunakan RME manual.');
        const format = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm'].find(type => MediaRecorder.isTypeSupported(type));
        if (! format) throw new Error('Format audio browser belum didukung. Gunakan browser lain atau RME manual.');
        this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        this.stream.getAudioTracks().forEach(track => track.addEventListener('ended', () => {
            if (this.wantRecording) { this.stopLocal(); this.error(new Error('Mikrofon terputus. Tinjau upload lalu selesaikan sesi atau lanjut rekam.')); }
        }));
        this.wantRecording = true;
        this.format = format;
        this.epoch++;
        this.recordPart();
    }

    recordPart() {
        // shortcut: rotasi klip mandiri dapat berjeda; ukur pada pilot sebelum menjanjikan rekaman kontinu.
        if (! this.wantRecording) return;
        const chunks = [];
        const epoch = this.epoch;
        const recorder = new MediaRecorder(this.stream, { mimeType: this.format });
        this.recorder = recorder;
        let partStopped;
        this.partStopped = new Promise(resolve => { partStopped = resolve; });
        this.partStarted = performance.now();
        recorder.addEventListener('dataavailable', event => { if (event.data.size > 0) chunks.push(event.data); });
        recorder.addEventListener('error', () => { this.stopLocal(); this.error(new Error('Perekaman gagal. Tinjau segmen yang sudah tersimpan dan gunakan RME manual bila perlu.')); });
        recorder.addEventListener('stop', () => {
            this.recordedMs += performance.now() - this.partStarted;
            if (! this.discard && chunks.length) {
                this.pending.push({ number: this.nextNumber++, blob: new Blob(chunks, { type: this.format }) });
                this.flushUploads().catch(error => { this.stopLocal(); this.error(error); });
            }
            partStopped();
            if (this.wantRecording && epoch === this.epoch) {
                if (this.pending.length >= 4 || this.recordedMs >= Number(this.root.dataset.maxSeconds) * 1000) {
                    this.stopLocal();
                    this.error(new Error('Batas rekaman atau upload tertunda tercapai. Selesaikan sesi setelah seluruh segmen terkirim.'));
                } else this.recordPart();
            }
            this.render();
        }, { once: true });
        recorder.start();
        this.rotate = setTimeout(() => { if (recorder.state === 'recording') recorder.stop(); }, 30000);
        this.render();
    }

    async stopLocal() {
        this.wantRecording = false;
        this.epoch++;
        clearTimeout(this.rotate);
        const recorder = this.recorder;
        const stopped = this.partStopped ?? Promise.resolve();
        if (recorder && recorder.state !== 'inactive') recorder.stop();
        this.stream?.getTracks().forEach(track => track.stop());
        await stopped;
        this.render();
    }

    async flushUploads() {
        // shortcut: blob tertunda hanya di memori halaman; gunakan penyimpanan terenkripsi jika pemulihan setelah reload diperlukan.
        if (this.uploading) return this.uploading;
        this.uploading = (async () => {
            while (this.pending.length && ! this.discard) {
                const part = this.pending[0];
                const data = new FormData();
                data.append('audio', part.blob, 'segment.' + (part.blob.type.includes('mp4') ? 'mp4' : 'webm'));
                data.append('segment_no', String(part.number));
                await this.request('/' + this.session.id + '/segments', data);
                this.pending.shift();
                this.render();
            }
        })();
        try { await this.uploading; }
        finally { this.uploading = null; }
    }

    async finish() {
        this.finished = true;
        await this.stopLocal();
        this.state = 'uploading';
        this.render();
        await this.flushUploads();
        if (this.nextNumber <= 1) throw new Error('Belum ada audio. Batalkan sesi lalu mulai rekaman baru atau gunakan RME manual.');
        this.accept(await this.request('/' + this.session.id + '/stop', { expected_segments: this.nextNumber - 1 }));
    }

    async fillSoap() {
        const soap = this.session.draft.soap;
        const payload = { soap, draft_version: this.session.draft.version };
        const form = document.querySelector('[data-rme-form]');
        const client = form?.rmeAutosave;
        try {
            payload.fields = [...this.root.querySelectorAll('[data-voice-select]:checked')].map(field => field.dataset.voiceSelect);
            if (! payload.fields.length || ! this.find('[data-voice-confirm]').checked) throw new Error('Pilih field dan centang persetujuan review dokter.');
            if (client?.hasPendingPhotos()) throw new Error('Simpan foto melalui form RME sebelum menerapkan draft Voice.');
            if (client && ! await client.flush()) throw new Error('Simpan perubahan RME terlebih dahulu sebelum menerapkan draft Voice.');
            if (! form?.elements.record_version) throw new Error('Editor RME tidak tersedia. Muat ulang dan tinjau status kunjungan.');
            form.inert = true;
            if (client) client.submitting = true;
            payload.record_version = Number(form.elements.record_version.value);
            payload.review_confirmed = true;
            const overwrites = payload.fields.some(key => form.elements[key]?.value && form.elements[key].value !== (soap[key] ?? ''));
            payload.confirm_overwrite = overwrites && window.confirm('Field RME terpilih sudah berisi data. Ganti hanya field terpilih dengan draft Voice yang telah Anda tinjau?');
            if (overwrites && ! payload.confirm_overwrite) return;
            this.accept(await this.request('/' + this.session.id + '/approve', payload));
            window.location.hash = 'rme-general-heading';
            window.location.reload();
        } finally {
            if (form) form.inert = false;
            if (client) client.submitting = false;
        }
    }

    showResults() {
        const payload = this.session.draft.payload;
        for (const field of this.root.querySelectorAll('[data-voice-value]')) field.textContent = this.session.draft.soap?.[field.dataset.voiceValue] || 'Belum tercatat';
        const evidence = facts => facts.map(item => 'Segmen ' + item.segment_no + ': “' + item.quote + '”').join('\n');
        for (const field of this.root.querySelectorAll('[data-voice-evidence]')) field.textContent = evidence(payload?.soap?.[field.dataset.voiceEvidence]?.evidence ?? []);
        this.find('[data-voice-warnings]').textContent = [...(payload?.uncertain_items ?? []).map(text => 'Perlu review: ' + text), ...(payload?.missing_information ?? []).map(text => 'Belum tercatat: ' + text)].join('\n') || 'Tetap verifikasi seluruh hasil terhadap konsultasi.';
        const list = (target, rows) => {
            this.find(target).replaceChildren(...(rows.length ? rows : ['Belum ada data.']).map(text => {
                const item = document.createElement('p');
                item.className = 'voice-result';
                item.textContent = text;
                return item;
            }));
        };
        list('[data-voice-transcript]', this.session.segments.map(segment => 'Segmen ' + segment.segment_no + '\n' + (segment.text ?? 'Transkrip belum tersedia atau sudah dihapus sesuai retensi.')));
        list('[data-voice-diagnoses]', (payload?.diagnoses ?? []).map(item => item.name + (item.code ? ' (' + item.code + ')' : '') + '\n' + evidence(item.evidence)));
        list('[data-voice-orders]', [
            ...(payload?.medications ?? []).map(item => 'Obat: ' + [item.name, item.dosage, item.unit, item.frequency, item.route, item.duration].filter(Boolean).join(' · ') + '\n' + evidence(item.evidence)),
            ...(payload?.procedures ?? []).map(item => 'Tindakan: ' + item.name + ' · ' + ({ planned: 'Direncanakan', performed: 'Disebut sudah dilakukan', uncertain: 'Belum jelas' }[item.status]) + '\n' + evidence(item.evidence)),
        ]);
    }

    tab(name) {
        for (const tab of this.root.querySelectorAll('[data-voice-tab]')) {
            tab.setAttribute('aria-selected', String(tab.dataset.voiceTab === name));
            tab.tabIndex = tab.dataset.voiceTab === name ? 0 : -1;
        }
        for (const pane of this.root.querySelectorAll('[data-voice-pane]')) pane.hidden = pane.dataset.voicePane !== name;
    }

    error(error) {
        if (this.state === 'recording' && ! this.wantRecording) this.state = 'paused';
        const messages = { NotAllowedError: 'Izin mikrofon ditolak. Aktifkan izin atau gunakan RME manual.', NotFoundError: 'Mikrofon tidak ditemukan. Sambungkan perangkat atau gunakan RME manual.', NotReadableError: 'Mikrofon sedang digunakan atau tidak dapat dibaca. Periksa perangkat.' };
        this.find('[data-voice-error]').textContent = messages[error.name] ?? error.message ?? 'ClinicOS Voice belum dapat digunakan.';
        this.render();
    }

    get unsaved() { return this.wantRecording || this.pending.length > 0; }

    destroy() {
        this.discard = true;
        clearTimeout(this.poll);
        this.stopLocal();
    }
}

if (typeof document !== 'undefined') {
    let voice;
    const initialize = () => {
        const root = document.querySelector('[data-voice-panel]');
        if (voice?.root === root) return;
        voice?.destroy();
        voice = root ? new VoiceScribe(root) : null;
    };
    document.addEventListener('DOMContentLoaded', initialize);
    document.addEventListener('livewire:navigated', initialize);
    window.addEventListener('beforeunload', event => { if (voice?.unsaved) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pagehide', () => voice?.destroy());
    document.addEventListener('livewire:navigate', event => {
        if (voice?.unsaved && ! window.confirm('Rekaman, upload atau draft Voice belum selesai. Tinggalkan halaman? Segmen yang belum terkirim dapat hilang.')) event.preventDefault();
    });
}
