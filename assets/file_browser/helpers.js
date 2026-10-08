/*
 * gm-file-browser: JSON requests, labels, formatting and alerts.
 */
const ICONS = {
    pdf: 'bi-file-earmark-pdf', zip: 'bi-file-earmark-zip', gz: 'bi-file-earmark-zip', rar: 'bi-file-earmark-zip', '7z': 'bi-file-earmark-zip',
    doc: 'bi-file-earmark-word', docx: 'bi-file-earmark-word', odt: 'bi-file-earmark-word',
    xls: 'bi-file-earmark-excel', xlsx: 'bi-file-earmark-excel', ods: 'bi-file-earmark-excel', csv: 'bi-file-earmark-spreadsheet',
    ppt: 'bi-file-earmark-ppt', pptx: 'bi-file-earmark-ppt', odp: 'bi-file-earmark-ppt',
    txt: 'bi-file-earmark-text',
};

export default {
    async request(url, { method = 'GET', json, signal } = {}) {
        const headers = { Accept: 'application/json' };

        if (method !== 'GET') {
            headers['X-CSRF-Token'] = this.configValue.csrfToken;
        }

        if (json !== undefined) {
            headers['Content-Type'] = 'application/json';
        }

        try {
            const response = await fetch(url, { method, headers, body: json === undefined ? undefined : JSON.stringify(json), credentials: 'same-origin', signal });
            const data = response.status === 204 ? null : await response.json().catch(() => null);

            return { ok: response.ok && (response.status === 204 || data !== null), status: response.status, data };
        } catch {
            return { ok: false, status: 0, data: null };
        }
    },

    fileUrl(id) {
        return this.configValue.urls.show.replace(this.configValue.idPlaceholder, id);
    },

    errorOf(response) {
        return response.data?.error ?? this.label('error.generic');
    },

    alert(type, html, autoHide = false) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `${html}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="${this.esc(this.label('action.close'))}"></button>`;
        this.alertsTarget.append(alert);

        if (autoHide) {
            setTimeout(() => window.bootstrap.Alert.getOrCreateInstance(alert).close(), 4000);
        }
    },

    label(key, parameters = {}) {
        return Object.entries(parameters).reduce(
            (text, [name, value]) => text.replaceAll(`%${name}%`, value),
            this.labelsValue[key] ?? key,
        );
    },

    esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => `&#${character.charCodeAt(0)};`);
    },

    formatSize(bytes) {
        const units = ['byte', 'kilobyte', 'megabyte', 'gigabyte'];
        let size = bytes;
        let unit = 0;

        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024;
            unit++;
        }

        return new Intl.NumberFormat(document.documentElement.lang || undefined, {
            style: 'unit', unit: units[unit], unitDisplay: 'short', maximumFractionDigits: unit > 1 ? 1 : 0,
        }).format(size);
    },

    formatDate(value) {
        return value
            ? new Intl.DateTimeFormat(document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
            : '';
    },

    icon(file) {
        if (ICONS[file.extension]) {
            return ICONS[file.extension];
        }

        if (file.mimeType.startsWith('video/')) {
            return 'bi-file-earmark-play';
        }

        return file.mimeType.startsWith('audio/') ? 'bi-file-earmark-music' : 'bi-file-earmark';
    },
};
