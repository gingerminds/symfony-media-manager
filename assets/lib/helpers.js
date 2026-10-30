/*
 * Labels, escaping and formatting of the picker widgets (files, medias).
 */
const ICONS = {
    pdf: 'bi-file-earmark-pdf', zip: 'bi-file-earmark-zip', gz: 'bi-file-earmark-zip', rar: 'bi-file-earmark-zip', '7z': 'bi-file-earmark-zip',
    doc: 'bi-file-earmark-word', docx: 'bi-file-earmark-word', odt: 'bi-file-earmark-word',
    xls: 'bi-file-earmark-excel', xlsx: 'bi-file-earmark-excel', ods: 'bi-file-earmark-excel', csv: 'bi-file-earmark-spreadsheet',
    ppt: 'bi-file-earmark-ppt', pptx: 'bi-file-earmark-ppt', odp: 'bi-file-earmark-ppt',
    txt: 'bi-file-earmark-text',
};

export default {

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
