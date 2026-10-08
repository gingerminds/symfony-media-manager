import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

import helpers from '../file_browser/helpers.js';

/*
 * FilePickerType widget (form/file_picker_theme.html.twig): the picked files, kept as comma
 * separated ids in the hidden input, picked in the library modal (loaded once per page, shared by
 * every field), removed one by one and, when several are allowed, reordered by drag & drop.
 * Dispatches `gm-file-picker:change` (detail: {files}).
 */

// Library modal of the page: {element, browser, field (the field picking)}.
let modal = null;

export default class FilePickerController extends Controller {
    static targets = ['input', 'items', 'button', 'buttonLabel'];

    static values = {
        url: String,
        multiple: Boolean,
        accept: Array,
        types: Array,
        startPath: String,
        files: Array,
        previewUrl: String,
        idPlaceholder: String,
        labels: Object,
    };

    connect() {
        this.files = [...this.filesValue];
        this.render();

        if (this.multipleValue) {
            this.sortable = Sortable.create(this.itemsTarget, {
                handle: '.gm-file-picker-handle',
                animation: 150,
                onEnd: () => this.reorder(),
            });
        }
    }

    disconnect() {
        this.sortable?.destroy();

        if (modal?.field === this) {
            modal.field = null;
        }
    }

    async open() {
        this.buttonTarget.disabled = true;

        try {
            const { element, browser } = await this.loadModal();
            modal.field = this;
            browser.startPicking({ accept: this.acceptValue, types: this.typesValue, multiple: this.multipleValue, startPath: this.startPathValue });
            window.bootstrap.Modal.getOrCreateInstance(element).show();
        } finally {
            this.buttonTarget.disabled = false;
        }
    }

    // Kept until a navigation replaces the page body.
    async loadModal() {
        if (modal && document.body.contains(modal.element)) {
            return modal;
        }

        const response = await fetch(this.urlValue, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(`The file library could not be loaded (${response.status}).`);
        }

        const template = document.createElement('template');
        template.innerHTML = (await response.text()).trim();
        const element = template.content.firstElementChild;
        document.body.append(element);

        const browserElement = element.querySelector('[data-controller~="gm-file-browser"]');
        let browser = null;

        // Stimulus connects the new controller on the next mutation records.
        for (let attempt = 0; attempt < 50 && !browser; attempt++) {
            await new Promise((resolve) => requestAnimationFrame(resolve));
            browser = this.application.getControllerForElementAndIdentifier(browserElement, 'gm-file-browser');
        }

        modal = { element, browser, field: null };
        element.addEventListener('gm-file-browser:pick', (event) => modal.field?.picked(event.detail.files));

        return modal;
    }

    picked(files) {
        if (this.multipleValue) {
            const ids = new Set(this.files.map((file) => file.id));
            this.files.push(...files.filter((file) => !ids.has(file.id)));
        } else {
            this.files = files.slice(0, 1);
        }

        this.render();
        this.changed();
    }

    remove({ params: { id } }) {
        this.files = this.files.filter((file) => file.id !== id);
        this.render();
        this.changed();
    }

    reorder() {
        const order = [...this.itemsTarget.children].map((card) => card.dataset.fileId);
        this.files.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
        this.changed();
    }

    changed() {
        this.inputTarget.value = this.files.map((file) => file.id).join(',');
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.dispatch('change', { detail: { files: this.files } });
    }

    render() {
        this.inputTarget.value = this.files.map((file) => file.id).join(',');
        this.itemsTarget.innerHTML = this.files.map((file) => this.item(file)).join('');
        this.itemsTarget.classList.toggle('d-none', this.files.length === 0);

        if (this.multipleValue) {
            this.buttonLabelTarget.textContent = this.label('add');
        } else {
            this.buttonLabelTarget.textContent = this.label(this.files.length > 0 ? 'replace' : 'choose');
        }
    }

    item(file) {
        const handle = this.multipleValue
            ? `<span class="gm-file-picker-handle" title="${this.esc(this.label('reorder'))}"><i class="bi bi-grip-vertical"></i></span>`
            : '';

        return `
            <div class="gm-file-picker-card" data-file-id="${this.esc(file.id)}">
                ${handle}
                <button type="button" class="btn btn-sm btn-light gm-file-picker-remove" title="${this.esc(this.label('remove'))}" aria-label="${this.esc(this.label('remove'))}"
                        data-action="gm-file-picker#remove" data-gm-file-picker-id-param="${this.esc(file.id)}"><i class="bi bi-x-lg"></i></button>
                <a href="${this.esc(file.url)}" target="_blank" rel="noopener" class="gm-file-picker-thumb" title="${this.esc(file.name)}">${this.thumbnail(file)}</a>
                <span class="gm-file-picker-info">
                    <span class="text-truncate" title="${this.esc(file.name)}">${this.esc(file.name)}</span>
                    <span class="small text-muted">${this.esc(this.formatSize(file.size))}</span>
                </span>
            </div>`;
    }

    thumbnail(file) {
        if (!file.isImage) {
            return `<i class="bi ${this.icon(file)}"></i>`;
        }

        const src = this.previewUrlValue ? this.previewUrlValue.replace(this.idPlaceholderValue, file.id) : (file.thumbnailUrl ?? file.url);

        return `<img src="${this.esc(src)}" alt="">`;
    }
}

Object.assign(FilePickerController.prototype, {
    esc: helpers.esc,
    formatSize: helpers.formatSize,
    icon: helpers.icon,
    label: helpers.label,
});
