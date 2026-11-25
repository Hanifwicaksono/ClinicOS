export class RmeWorkspace {
    constructor(form) {
        this.form = form;
        this.tabs = [...form.querySelectorAll('[data-rme-tab]')];
        this.panels = [...form.querySelectorAll('[data-rme-panel]')];
        const header = document.querySelector('.rme-patient-header')?.closest('header');
        if (header) {
            this.headerObserver = new ResizeObserver(() => {
                form.style.setProperty('--rme-sticky-top', `${header.offsetHeight + (parseFloat(getComputedStyle(header).top) || 0) + 8}px`);
            });
            this.headerObserver.observe(header);
        }
        form.addEventListener('click', event => {
            const tab = event.target.closest('[data-rme-tab]');
            if (tab) this.select(tab.dataset.rmeTab);
            if (event.target.closest('[data-rme-review]')) this.buildReview();
            const error = event.target.closest('[data-rme-error-field]');
            if (error) this.reveal(this.field(error.dataset.rmeErrorField));
            const add = event.target.closest('[data-rme-add]');
            if (add) requestAnimationFrame(() => {
                const rows = form.querySelectorAll(`[data-rme-row="${add.dataset.rmeAdd}"]`);
                rows[rows.length - 1]?.querySelector('input')?.focus();
            });
        });
        form.addEventListener('keydown', event => {
            const index = this.tabs.indexOf(event.target);
            if (index < 0 || ! ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? this.tabs.length - 1
                : (index + (event.key === 'ArrowRight' ? 1 : this.tabs.length - 1)) % this.tabs.length;
            this.select(this.tabs[next].dataset.rmeTab);
            this.tabs[next].focus();
        });
        form.addEventListener('invalid', event => this.reveal(event.target), true);
        form.addEventListener('rme-validation-errors', event => this.showErrors(event.detail));
        for (const type of ['input', 'change', 'rme-changed']) form.addEventListener(type, () => requestAnimationFrame(() => this.update()));
        this.hashChange = () => {
            const id = window.location.hash.slice(1);
            if (id) this.reveal(document.getElementById(id), false);
        };
        window.addEventListener('hashchange', this.hashChange);
        this.openSoap = () => this.reveal(form.elements.chief_complaint);
        window.addEventListener('rme-open-soap', this.openSoap);
        this.select('soap');
        this.hashChange();
        const invalid = form.querySelector('[aria-invalid="true"]');
        if (invalid) this.reveal(invalid);
        this.update();
        form.dataset.workspaceReady = 'true';
    }

    field(key) {
        const name = key.replace(/\.([^.]+)/g, '[$1]');
        return [...this.form.elements].find(field => field.name === name);
    }

    select(name) {
        for (const tab of this.tabs) {
            const selected = tab.dataset.rmeTab === name;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        }
        for (const panel of this.panels) panel.hidden = panel.dataset.rmePanel !== name;
    }

    reveal(field, focus = true) {
        if (! field || ! this.form.contains(field) || field.type === 'hidden' || field.closest('[role="dialog"]')) return;
        const panel = field.closest('[data-rme-panel]');
        if (panel) this.select(panel.dataset.rmePanel);
        for (let parent = field.parentElement; parent && parent !== this.form; parent = parent.parentElement) {
            if (parent.tagName === 'DETAILS') parent.open = true;
        }
        if (focus) {
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'confirm-finalize-visit' }));
            // Wait for the modal's leave transition before positioning the field below the sticky tabs.
            setTimeout(() => {
                field.focus({ preventScroll: true });
                field.scrollIntoView({ block: 'center', behavior: 'instant' });
                const tabBottom = this.form.querySelector('[data-rme-tabs]').getBoundingClientRect().bottom;
                const overlap = field.getBoundingClientRect().top - tabBottom - 8;
                if (overlap < 0) window.scrollBy({ top: overlap, behavior: 'instant' });
            }, 350);
        }
    }

    showErrors({ errors = {}, focus = false }) {
        const summary = this.form.querySelector('[data-rme-validation-summary]');
        summary.replaceChildren();
        summary.hidden = Object.keys(errors).length === 0;
        for (const [key, messages] of Object.entries(errors)) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.rmeErrorField = key;
            button.className = 'block min-h-11 text-left text-sm font-semibold underline';
            button.textContent = messages[0];
            summary.append(button);
        }
        this.update();
        if (focus) this.reveal(Object.keys(errors).map(key => this.field(key)).find(field => field && field.type !== 'hidden'));
    }

    soapFields() {
        return ['subjective', 'assessment', 'plan'].map(name => this.form.elements[name]);
    }

    hasFieldError(field) {
        return field.getAttribute('aria-invalid') === 'true'
            || (field.willValidate && ! field.validity.valid && (String(field.value).trim() !== '' || field.validity.badInput
                || (field.required && field.closest('[data-rme-panel]')?.dataset.rmePanel !== 'soap')));
    }

    buildReview() {
        const list = (selector, rows, empty) => {
            const target = this.form.querySelector(selector);
            target.replaceChildren();
            for (const value of rows.length ? rows : [empty]) {
                const item = document.createElement('li');
                item.className = 'whitespace-pre-line [overflow-wrap:anywhere]';
                item.textContent = value;
                target.append(item);
            }
        };
        const value = (row, key) => row.querySelector(`[name$="[${key}]"]`)?.value.trim() || '';
        const rows = type => [...this.form.querySelectorAll(`[data-rme-row="${type}"]`)];
        const soap = this.soapFields();
        const missing = soap.filter(field => ! field.value.trim() || ! field.validity.valid);
        this.form.querySelector('[data-rme-review-soap]').textContent = missing.length
            ? `Perlu dilengkapi: ${missing.map(field => ({ subjective: 'S · Subjektif', assessment: 'A · Assessment', plan: 'P · Rencana' })[field.name]).join(', ')}.`
            : '3/3 isian wajib terisi. Tinjau isi SOAP sebelum mengesahkan.';
        list('[data-rme-review-vital]', [...this.form.querySelectorAll('[name^="vital_signs["]')].filter(field => field.value.trim()).map(field => {
            const label = this.form.querySelector(`label[for="${field.id}"]`).textContent.trim();
            const unit = field.parentElement.querySelector('span')?.textContent.trim() || '';
            return `${label}: ${field.value} ${unit}`.trim();
        }), 'Belum dicatat · opsional.');
        list('[data-rme-review-clinical]', [
            ...rows('diagnosis').filter(row => value(row, 'name')).map(row => `Diagnosis: ${value(row, 'name')}${value(row, 'code') ? ` (${value(row, 'code')})` : ''}${row.querySelector('[type="checkbox"]').checked ? ' · utama' : ''}`),
            ...rows('treatment').filter(row => value(row, 'name')).map(row => `Tindakan: ${value(row, 'name')} · jumlah ${value(row, 'quantity') || 'belum diisi'}`),
        ], 'Belum dicatat · opsional.');
        const medicines = rows('medicine').filter(row => value(row, 'medicine_name'));
        list('[data-rme-review-prescription]', medicines.map(row => `${value(row, 'medicine_name')} · ${value(row, 'dosage') || 'Dosis belum diisi'} · ${value(row, 'frequency') || 'Frekuensi belum diisi'} · ${value(row, 'quantity') || 'Jumlah belum diisi'} ${value(row, 'unit')}${value(row, 'instructions') ? `\n${value(row, 'instructions')}` : ''}`), 'Tidak ada obat pada resep.');
        const acknowledgement = this.form.elements.critical_allergies_acknowledged;
        this.form.querySelector('[data-rme-review-allergy]').textContent = acknowledgement && medicines.length
            ? (acknowledgement.checked ? 'Peninjauan alergi kritis sudah dikonfirmasi untuk resep ini.' : 'Wajib: konfirmasi peninjauan alergi kritis belum diberikan.')
            : `${document.querySelector('[data-allergy-state]')?.textContent.trim().replace(/\s+/g, ' ') || 'Riwayat alergi perlu ditinjau'}. Tetap tinjau alergi sebelum obat atau tindakan.`;
        const invalid = [...this.form.elements].filter(field => ! field.closest('[role="dialog"]')
            && (this.hasFieldError(field) || (field.willValidate && field.required && (! field.validity.valid || ! field.value.trim()))));
        this.form.querySelector('[data-rme-review-validation]').textContent = invalid.length
            ? `${invalid.length} isian perlu diperiksa. Kembali ke form untuk melengkapinya; data tetap tersedia.`
            : 'Tidak ada peringatan pada isian saat ini. Pastikan catatan dan resep sudah ditinjau.';
    }

    update() {
        const filled = fields => fields.filter(field => String(field.value).trim() !== '');
        const soap = this.soapFields();
        const validSoap = filled(soap).filter(field => field.validity.valid).length;
        const medicines = filled([...this.form.querySelectorAll('[name$="[medicine_name]"]')]);
        const descriptions = {
            soap: `${validSoap}/3 wajib`,
            vital: `${filled([...this.form.querySelectorAll('[name^="vital_signs["]')]).length} tercatat`,
            clinical: `${filled([...this.form.querySelectorAll('[name^="diagnoses["][name$="[name]"]')]).length} diagnosis · ${filled([...this.form.querySelectorAll('[name^="treatments["][name$="[name]"]')]).length} tindakan`,
            prescription: medicines.length ? `${medicines.length} obat · tinjau` : 'Opsional',
        };
        for (const tab of this.tabs) {
            const panel = this.panels.find(panel => panel.dataset.rmePanel === tab.dataset.rmeTab);
            const invalid = [...panel.querySelectorAll('input, textarea, select')].filter(field => this.hasFieldError(field)).length;
            tab.querySelector('[data-rme-tab-status]').textContent = invalid ? `${invalid} perlu diperiksa` : descriptions[tab.dataset.rmeTab];
            tab.dataset.hasErrors = String(invalid > 0);
        }
    }

    destroy() {
        this.headerObserver?.disconnect();
        window.removeEventListener('hashchange', this.hashChange);
        window.removeEventListener('rme-open-soap', this.openSoap);
    }
}

if (typeof document !== 'undefined') {
    let workspace;
    const initialize = () => {
        const form = document.querySelector('[data-rme-workspace]');
        if (workspace?.form === form) return;
        workspace?.destroy();
        workspace = form ? new RmeWorkspace(form) : null;
        if (form) form.rmeWorkspace = workspace;
    };
    document.addEventListener('DOMContentLoaded', initialize);
    document.addEventListener('livewire:navigated', initialize);
}
