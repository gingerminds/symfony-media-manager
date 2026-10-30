import { Controller } from '@hotwired/stimulus';

/*
 * The picker modals (files, medias): open() loads the Turbo frame of the modal for a field, a click
 * on a card picks its item (`toggle`, the item in the `item` param; toggled when several are
 * allowed), an upload picks the new file, and "Select" dispatches `gm-pick:pick` (detail: {items})
 * then closes the modal. The picked items are kept across the frame navigations; the cards carry
 * `data-pick-id`.
 */
export default class PickController extends Controller {
    static targets = ['summary', 'confirm'];

    static values = { none: String, selected: String };

    connect() {
        this.picked = new Map();
        this.multiple = false;
        this.allowEmpty = false;
    }

    // `selected`: the items picked already; `allowEmpty`: "Select" with none clears the field.
    open(url, { multiple = false, selected = [], allowEmpty = false } = {}) {
        this.picked = new Map(selected.map((item) => [String(item.id), item]));
        this.multiple = multiple;
        this.allowEmpty = allowEmpty;
        const frame = this.element.querySelector('turbo-frame');

        if (frame.src === url) {
            frame.reload();
        } else {
            frame.src = url;
        }

        this.refresh();
    }

    toggle({ params: { item } }) {
        this.pick(item);
    }

    uploaded({ detail: { file } }) {
        if (!this.picked.has(String(file.id))) {
            this.pick(file);
        }
    }

    pick(item) {
        const id = String(item.id);

        if (this.picked.has(id) && (this.multiple || this.allowEmpty)) {
            this.picked.delete(id);
        } else {
            if (!this.multiple) {
                this.picked.clear();
            }

            this.picked.set(id, item);
        }

        this.refresh();
    }

    refresh() {
        this.element.querySelectorAll('[data-pick-id]').forEach((card) => {
            card.classList.toggle('is-selected', this.picked.has(card.dataset.pickId));
        });

        const items = [...this.picked.values()];
        this.confirmTarget.disabled = items.length === 0 && !this.allowEmpty;

        if (items.length === 0) {
            this.summaryTarget.textContent = this.noneValue;
        } else {
            this.summaryTarget.textContent = this.multiple ? this.selectedValue.replace('%count%', items.length) : items[0].name;
        }
    }

    confirm() {
        this.dispatch('pick', { detail: { items: [...this.picked.values()] } });
        window.bootstrap.Modal.getOrCreateInstance(this.element).hide();
    }
}
