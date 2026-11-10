export function patientDuplicateReview(endpoint) {
    return {
        candidates: [], token: '', reviewed: false, busy: false, message: '', sequence: 0,
        init() {
            this.form = this.$el.closest('form');
            this.onInput = (event) => {
                if (! ['name', 'nik', 'phone', 'birth_date'].includes(event.target.name)) return;
                this.reviewed = false;
                this.token = '';
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.check(), 400);
            };
            this.onSubmit = async (event) => {
                if (this.allowSubmit || new FormData(this.form).get('patient_id')) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                if (this.checkingSubmit) return;
                this.checkingSubmit = true;
                clearTimeout(this.timer);
                const checked = await this.check();
                if (checked && (! this.candidates.length || this.reviewed)) {
                    this.allowSubmit = true;
                    this.form.requestSubmit(event.submitter ?? undefined);
                    this.allowSubmit = false;
                } else {
                    this.message ||= 'Tinjau kandidat pasien di bawah sebelum melanjutkan.';
                    this.$refs.feedback.focus();
                }
                this.checkingSubmit = false;
            };
            this.form.addEventListener('input', this.onInput);
            this.form.addEventListener('submit', this.onSubmit, true);
            this.check();
        },
        async check() {
            const sequence = ++this.sequence;
            const fields = new FormData(this.form);
            if (fields.get('patient_id')) return true;
            this.busy = true;
            this.message = '';
            try {
                const response = await fetch(endpoint, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(10000),
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': fields.get('_token') },
                    body: JSON.stringify(Object.fromEntries(['name', 'birth_date', 'nik', 'phone'].map(name => [name, fields.get(name) ?? '']))),
                });
                if (! response.ok) throw new Error('Kandidat pasien belum dapat diperiksa. Periksa data dan coba lagi.');
                const payload = await response.json();
                if (sequence !== this.sequence) return false;
                if (this.token !== payload.token) this.reviewed = false;
                this.candidates = payload.candidates;
                this.token = payload.token;
                await this.$nextTick();
                return true;
            } catch (error) {
                if (sequence === this.sequence) this.message = error.message;
                return false;
            } finally {
                if (sequence === this.sequence) this.busy = false;
            }
        },
        destroy() {
            clearTimeout(this.timer);
            this.form.removeEventListener('input', this.onInput);
            this.form.removeEventListener('submit', this.onSubmit, true);
        },
    };
}

if (typeof window !== 'undefined') window.patientDuplicateReview = patientDuplicateReview;
