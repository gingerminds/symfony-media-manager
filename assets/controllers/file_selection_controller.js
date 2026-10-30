import { Controller } from '@hotwired/stimulus';

/*
 * Selection of the library browser: the "select all" of the page files, the count, and the toolbar
 * actions enabled by what is selected (move and delete: anything, rename: a single folder).
 */
export default class FileSelectionController extends Controller {
    static targets = ['all', 'count', 'file', 'directory', 'action', 'rename'];
    static values = { selected: String, none: String };

    connect() {
        this.refresh();
    }

    toggleAll() {
        this.fileTargets.forEach((file) => { file.checked = this.allTarget.checked; });
        this.refresh();
    }

    refresh() {
        const files = this.fileTargets.filter((file) => file.checked).length;
        const directories = this.directoryTargets.filter((directory) => directory.checked).length;
        const count = files + directories;

        if (this.hasAllTarget) {
            this.allTarget.checked = this.fileTargets.length > 0 && files === this.fileTargets.length;
            this.allTarget.indeterminate = files > 0 && files < this.fileTargets.length;
            this.allTarget.disabled = this.fileTargets.length === 0;
        }

        if (this.hasCountTarget) {
            this.countTarget.textContent = count > 0 ? this.selectedValue.replace('__count__', count) : this.noneValue;
        }

        this.actionTargets.forEach((button) => { button.disabled = count === 0; });
        this.renameTargets.forEach((button) => { button.disabled = !(directories === 1 && files === 0); });
    }
}
