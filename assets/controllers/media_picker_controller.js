import { Controller } from '@hotwired/stimulus';

import helpers from '../file_browser/helpers.js';

/*
 * Media picker modal (components/media/_picker_modal.html.twig), opened by the gm-media-select fields:
 * search, category (subcategories included), "Load more". A click toggles a media (replaces it when
 * one is allowed); "Select" dispatches `gm-media-picker:pick` (detail: {medias}) and closes the modal.
 */
export default class MediaPickerController extends Controller {
    static targets = ['search', 'searchColumn', 'category', 'categoryColumn', 'results', 'more', 'summary', 'confirm'];

    static values = {
        searchUrl: String,
        labels: Object,
    };

    connect() {
        this.found = new Map();
        this.picked = new Map();
    }

    disconnect() {
        clearTimeout(this.searchTimeout);
        this.request?.abort();
    }

    // Called each time a field opens the modal; `categories`: allowed ids, one locks the filter.
    start({ multiple = false, categories = [], perPage = 24, selected = [] } = {}) {
        this.multiple = multiple;
        this.allowed = categories.map(String);
        this.perPage = perPage;
        this.picked = new Map(selected.map((media) => [media.id, media]));
        this.searchTarget.value = '';
        this.restrictCategories();
        this.reload();
        this.renderSummary();
    }

    restrictCategories() {
        const locked = this.allowed.length === 1;

        [...this.categoryTarget.options].forEach((option) => {
            const ids = [option.value, ...(option.dataset.ancestors ?? '').split(' ').filter(Boolean)];
            option.hidden = option.value !== '' && this.allowed.length > 0 && !ids.some((id) => this.allowed.includes(id));
        });
        this.categoryTarget.value = locked ? this.allowed[0] : '';
        this.categoryColumnTarget.classList.toggle('d-none', locked);
    }

    search() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => this.reload(), 300);
    }

    reload() {
        this.page = 1;
        this.found = new Map();
        this.resultsTarget.innerHTML = this.message('loading');
        this.load();
    }

    more() {
        this.page++;
        this.load();
    }

    async load() {
        const params = new URLSearchParams({ page: this.page, per_page: this.perPage });
        const search = this.searchTarget.value.trim();

        if (search) {
            params.set('search', search);
        }

        if (this.categoryTarget.value) {
            params.set('category', this.categoryTarget.value);
        }

        this.allowed.forEach((id) => params.append('categories[]', id));

        this.request?.abort();
        const request = new AbortController();
        this.request = request;
        let data = null;

        try {
            const response = await fetch(`${this.searchUrlValue}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: request.signal });
            data = response.ok ? await response.json() : null;
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
        }

        if (this.request !== request) {
            return;
        }

        if (!data) {
            this.resultsTarget.innerHTML = this.message('error', 'text-danger');

            return;
        }

        if (this.page === 1) {
            this.resultsTarget.innerHTML = data.items.length === 0 ? this.message('empty') : '';
        }

        data.items.forEach((media) => this.found.set(media.id, media));
        this.resultsTarget.insertAdjacentHTML('beforeend', data.items.map((media) => this.card(media)).join(''));
        this.moreTarget.classList.toggle('d-none', !data.hasMore);
    }

    toggle({ params: { id } }) {
        const media = this.found.get(id);

        if (this.picked.has(id)) {
            this.picked.delete(id);
        } else if (media) {
            if (!this.multiple) {
                this.picked.clear();
            }

            this.picked.set(id, media);
        }

        this.resultsTarget.querySelectorAll('[data-media-id]').forEach((card) => {
            card.classList.toggle('is-selected', this.picked.has(Number(card.dataset.mediaId)));
        });
        this.renderSummary();
    }

    renderSummary() {
        const medias = [...this.picked.values()];
        this.confirmTarget.disabled = !this.multiple && medias.length === 0;

        if (medias.length === 0) {
            this.summaryTarget.textContent = this.label('none');
        } else {
            this.summaryTarget.textContent = this.multiple ? this.label('selected', { count: medias.length }) : medias[0].name;
        }
    }

    confirm() {
        this.dispatch('pick', { detail: { medias: [...this.picked.values()] } });
        window.bootstrap.Modal.getOrCreateInstance(this.element).hide();
    }

    card(media) {
        const preview = media.thumbnailUrl
            ? `<img src="${this.esc(media.thumbnailUrl)}" alt="" loading="lazy">`
            : `<i class="bi ${media.file ? this.icon(media.file) : 'bi-file-earmark'}"></i>`;

        return `
            <div class="gm-file-card${this.picked.has(media.id) ? ' is-selected' : ''}" data-media-id="${this.esc(media.id)}">
                <button type="button" class="gm-file-card-preview" title="${this.esc(media.name)}"
                        data-action="gm-media-picker#toggle" data-gm-media-picker-id-param="${this.esc(media.id)}">${preview}</button>
                <span class="gm-file-card-body">
                    <span class="gm-file-card-name" title="${this.esc(media.name)}">${this.esc(media.name)}</span>
                    <span class="small text-muted text-truncate">${this.esc(media.category ?? media.code ?? '')}</span>
                </span>
            </div>`;
    }

    message(key, className = 'text-muted') {
        return `<div class="${className} py-5 text-center gm-file-grid-message">${this.esc(this.label(key))}</div>`;
    }
}

Object.assign(MediaPickerController.prototype, {
    esc: helpers.esc,
    icon: helpers.icon,
    label: helpers.label,
});
