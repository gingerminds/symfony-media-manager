import { Controller } from '@hotwired/stimulus';

/*
 * The library modal of the FilePickerType fields (components/file/_picker_modal.html.twig):
 * open() loads the browser for a field, a click on a file picks it (toggles it when several are
 * allowed), an upload picks the new file, and "Select" dispatches `gm-file-pick:pick` (detail:
 * {files}) then closes the modal. The picked files are kept across the frame navigations.
 */
export default class FilePickController extends Controller {
    static targets = ['summary', 'confirm'];

    static values = { none: String, selected: String };

    connect() {
        this.picked = new Map();
        this.multiple = false;
    }

    open(url, multiple) {
        this.picked.clear();
        this.multiple = multiple;
        const frame = this.element.querySelector('turbo-frame');

        if (frame.src === url) {
            frame.reload();
        } else {
            frame.src = url;
        }

        this.refresh();
    }

    toggle({ params: { file } }) {
        this.pick(file);
    }

    uploaded({ detail: { file } }) {
        if (!this.picked.has(file.id)) {
            this.pick(file);
        }
    }

    pick(file) {
        if (this.multiple && this.picked.has(file.id)) {
            this.picked.delete(file.id);
        } else {
            if (!this.multiple) {
                this.picked.clear();
            }

            this.picked.set(file.id, file);
        }

        this.refresh();
    }

    refresh() {
        this.element.querySelectorAll('.gm-file-card[data-file-id]').forEach((card) => {
            card.classList.toggle('is-selected', this.picked.has(card.dataset.fileId));
        });

        const files = [...this.picked.values()];
        this.confirmTarget.disabled = files.length === 0;

        if (files.length === 0) {
            this.summaryTarget.textContent = this.noneValue;
        } else {
            this.summaryTarget.textContent = this.multiple ? this.selectedValue.replace('%count%', files.length) : files[0].name;
        }
    }

    confirm() {
        this.dispatch('pick', { detail: { files: [...this.picked.values()] } });
        window.bootstrap.Modal.getOrCreateInstance(this.element).hide();
    }
}
