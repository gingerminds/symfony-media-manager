/*
 * gm-file-browser: picker mode (components/file/_picker_modal.html.twig), opened by the
 * gm-file-picker fields. A click picks a file (toggles it when several are allowed), the
 * "Select" button dispatches `gm-file-browser:pick` with the picked files and closes the modal.
 * Only uploads and new folders are offered there: the other actions stay on the library page.
 */
export default {
    isPicker() {
        return this.modeValue === 'picker';
    },

    canManage() {
        return this.configValue.canEdit && !this.isPicker();
    },

    // Called each time a field opens the modal.
    startPicking({ accept = [], multiple = false, startPath = '' } = {}) {
        this.accept = accept;
        this.multiplePick = multiple;
        this.picked = new Map();

        if (this.hasUploadInputTarget) {
            this.uploadInputTarget.multiple = true;
            this.uploadInputTarget.accept = (accept.length > 0 ? accept : this.configValue.allowedMimes).join(',');
        }

        this.clearFilters();
        this.navigate(startPath || this.configValue.startPath || '');
        this.renderPicked();
    },

    pickFile({ params: { id } }) {
        const file = this.files.get(id);

        if (file) {
            this.pick(file);
        }

        this.openDetail(id);
    },

    async pickExisting({ params: { id } }) {
        const response = await this.request(this.fileUrl(id));

        if (response.ok) {
            this.pick(response.data);
        }
    },

    pick(file) {
        if (this.multiplePick && this.picked.has(file.id)) {
            this.picked.delete(file.id);
        } else {
            if (!this.multiplePick) {
                this.picked.clear();
            }

            this.picked.set(file.id, file);
        }

        this.renderPicked();
    },

    renderPicked() {
        this.renderSelection();

        if (!this.hasPickConfirmTarget) {
            return;
        }

        const files = [...this.picked.values()];
        this.pickConfirmTarget.disabled = files.length === 0;

        if (files.length === 0) {
            this.pickSummaryTarget.textContent = this.label('picker.none');
        } else {
            this.pickSummaryTarget.textContent = this.multiplePick ? this.label('picker.selected', { count: files.length }) : files[0].name;
        }
    },

    confirmPick() {
        this.dispatch('pick', { detail: { files: [...this.picked.values()] } });
        window.bootstrap.Modal.getOrCreateInstance(this.element.closest('.modal')).hide();
    },
};
