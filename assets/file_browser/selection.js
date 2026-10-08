/*
 * gm-file-browser: Selection of the files or of the folders of the page, never both: the toolbar
 * shows the actions of what is selected.
 */
export default {
    toggleSelection({ params: { id }, target }) {
        this.directorySelection.clear();

        if (target.checked) {
            this.selection.add(id);
        } else {
            this.selection.delete(id);
        }

        this.renderSelection();
    },

    toggleDirectorySelection({ params: { path }, target }) {
        this.selection.clear();

        if (target.checked) {
            this.directorySelection.add(path);
        } else {
            this.directorySelection.delete(path);
        }

        this.renderSelection();
    },

    togglePage() {
        this.directorySelection.clear();
        const ids = [...this.files.keys()];
        const all = ids.length > 0 && ids.every((id) => this.selection.has(id));
        ids.forEach((id) => (all ? this.selection.delete(id) : this.selection.add(id)));
        this.renderSelection();
    },

    renderSelection() {
        this.gridTarget.querySelectorAll('[data-file-id]').forEach((card) => {
            const selected = (this.isPicker() ? this.picked : this.selection).has(card.dataset.fileId);
            card.classList.toggle('is-selected', selected);
            const check = card.querySelector('.gm-file-card-check');

            if (check) {
                check.checked = selected;
            }
        });

        this.gridTarget.querySelectorAll('[data-directory-path]').forEach((card) => {
            const selected = this.directorySelection.has(card.dataset.directoryPath);
            card.classList.toggle('is-selected', selected);
            const check = card.querySelector('.gm-file-card-check');

            if (check) {
                check.checked = selected;
            }
        });

        if (!this.hasSelectAllTarget) {
            return;
        }

        const directories = this.directorySelection.size;

        const ids = [...this.files.keys()];
        const selected = ids.filter((id) => this.selection.has(id)).length;
        this.selectAllTarget.checked = ids.length > 0 && selected === ids.length;
        this.selectAllTarget.indeterminate = selected > 0 && selected < ids.length;
        this.selectAllTarget.disabled = ids.length === 0;
        this.selectionCountTarget.textContent = this.selection.size + directories > 0
            ? this.label('message.selected', { count: this.selection.size + directories })
            : this.label('action.select_page');
        this.selectionActionTargets.forEach((button) => {
            button.disabled = this.selection.size === 0;
        });
        this.fileActionsTarget.classList.toggle('d-none', directories > 0);
        this.directoryActionsTarget.classList.toggle('d-none', directories === 0);
        this.renameDirectoryButtonTarget.disabled = directories !== 1;
    },
};
