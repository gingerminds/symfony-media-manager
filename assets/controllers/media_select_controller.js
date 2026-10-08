import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

import helpers from '../file_browser/helpers.js';

/*
 * MediaSelectType widget (form/media_select_theme.html.twig): the picked medias, kept as comma
 * separated ids in the hidden input, picked in the media picker modal (loaded once per page, shared
 * by every field), removed one by one and, when several are allowed, reordered by drag & drop.
 * Dispatches `gm-media-select:change` (detail: {medias}).
 */

// Picker modal of the page: {element, picker, field (the field picking)}.
let modal = null;

export default class MediaSelectController extends Controller {
    static targets = ['input', 'items', 'button', 'buttonLabel'];

    static values = {
        url: String,
        multiple: Boolean,
        categories: Array,
        perPage: Number,
        medias: Array,
        labels: Object,
    };

    connect() {
        this.medias = [...this.mediasValue];
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
            const { element, picker } = await this.loadModal();
            modal.field = this;
            picker.start({ multiple: this.multipleValue, categories: this.categoriesValue, perPage: this.perPageValue, selected: this.medias });
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
            throw new Error(`The media picker could not be loaded (${response.status}).`);
        }

        const template = document.createElement('template');
        template.innerHTML = (await response.text()).trim();
        const element = template.content.firstElementChild;
        document.body.append(element);

        let picker = null;

        // Stimulus connects the new controller on the next mutation records.
        for (let attempt = 0; attempt < 50 && !picker; attempt++) {
            await new Promise((resolve) => requestAnimationFrame(resolve));
            picker = this.application.getControllerForElementAndIdentifier(element, 'gm-media-picker');
        }

        modal = { element, picker, field: null };
        element.addEventListener('gm-media-picker:pick', (event) => modal.field?.picked(event.detail.medias));

        return modal;
    }

    // The picker starts from the current selection: what it returns replaces it.
    picked(medias) {
        this.medias = this.multipleValue ? medias : medias.slice(0, 1);
        this.render();
        this.changed();
    }

    remove({ params: { id } }) {
        this.medias = this.medias.filter((media) => media.id !== id);
        this.render();
        this.changed();
    }

    reorder() {
        const order = [...this.itemsTarget.children].map((card) => Number(card.dataset.mediaId));
        this.medias.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
        this.changed();
    }

    changed() {
        this.inputTarget.value = this.medias.map((media) => media.id).join(',');
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.dispatch('change', { detail: { medias: this.medias } });
    }

    render() {
        this.inputTarget.value = this.medias.map((media) => media.id).join(',');
        this.itemsTarget.innerHTML = this.medias.map((media) => this.item(media)).join('');
        this.itemsTarget.classList.toggle('d-none', this.medias.length === 0);
        this.buttonLabelTarget.textContent = this.label(this.buttonLabel());
    }

    buttonLabel() {
        if (this.multipleValue) {
            return 'add';
        }

        return this.medias.length > 0 ? 'replace' : 'choose';
    }

    item(media) {
        const handle = this.multipleValue
            ? `<span class="gm-file-picker-handle" title="${this.esc(this.label('reorder'))}"><i class="bi bi-grip-vertical"></i></span>`
            : '';
        const icon = media.file ? this.icon(media.file) : 'bi-file-earmark';
        const thumbnail = media.thumbnailUrl
            ? `<img src="${this.esc(media.thumbnailUrl)}" alt="">`
            : `<i class="bi ${icon}"></i>`;

        return `
            <div class="gm-file-picker-card" data-media-id="${this.esc(media.id)}">
                ${handle}
                <button type="button" class="btn btn-sm btn-light gm-file-picker-remove" title="${this.esc(this.label('remove'))}" aria-label="${this.esc(this.label('remove'))}"
                        data-action="gm-media-select#remove" data-gm-media-select-id-param="${this.esc(media.id)}"><i class="bi bi-x-lg"></i></button>
                <span class="gm-file-picker-thumb" title="${this.esc(media.name)}">${thumbnail}</span>
                <span class="gm-file-picker-info">
                    <span class="text-truncate" title="${this.esc(media.name)}">${this.esc(media.name)}</span>
                    <span class="small text-muted text-truncate">${this.esc(media.code ?? '')}</span>
                </span>
            </div>`;
    }
}

Object.assign(MediaSelectController.prototype, {
    esc: helpers.esc,
    icon: helpers.icon,
    label: helpers.label,
});
