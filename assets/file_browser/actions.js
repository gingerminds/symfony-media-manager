/*
 * gm-file-browser: New folder and file actions, confirmed in the dialog.
 */
export default {
    createDirectory() {
        this.openDialog({
            title: this.label('action.new_directory'),
            body: this.inputField('field.directory_name', ''),
            onConfirm: async () => {
                const response = await this.request(this.configValue.urls.directory, { method: 'POST', json: { parent: this.path, name: this.dialogInput().value } });

                if (!response.ok) {
                    return this.dialogError(this.errorOf(response));
                }

                await this.refreshDirectory(this.path);
                await this.load();

                return true;
            },
        });
    },

    rename({ params: { id } }) {
        const file = this.files.get(id);
        const name = file ? file.name.replace(new RegExp(`\\.${file.extension}$`, 'i'), '') : '';

        this.openDialog({
            title: this.label('action.rename'),
            body: this.inputField('field.file_name', name, file?.extension ? `.${file.extension}` : ''),
            onConfirm: async () => {
                const response = await this.request(this.fileUrl(id), { method: 'PATCH', json: { name: this.dialogInput().value } });

                if (!response.ok) {
                    return this.dialogError(this.errorOf(response));
                }

                await this.load();
                await this.openDetail(id);

                return true;
            },
        });
    },

    moveSelection() {
        this.chooseDirectory([...this.selection]);
    },

    moveFile({ params: { id } }) {
        this.chooseDirectory([id]);
    },

    chooseDirectory(ids) {
        this.openChooser({
            title: this.label('action.move'),
            summary: (path) => this.label('message.move_to', { count: ids.length, path: path || this.label('root') }),
            onConfirm: async (path) => {
                const response = await this.request(this.configValue.urls.move, { method: 'POST', json: { ids, path } });

                if (!response.ok) {
                    return this.dialogError(this.errorOf(response));
                }

                this.alert('success', this.esc(this.label('message.moved', { count: response.data.length })), true);
                this.selection.clear();
                this.closeDetail();
                await this.load();

                return true;
            },
        });
    },

    deleteSelection() {
        this.confirmDelete([...this.selection]);
    },

    deleteFile({ params: { id } }) {
        this.confirmDelete([id]);
    },

    confirmDelete(ids) {
        this.openDialog({
            title: this.label('action.delete'),
            body: `<p class="mb-0">${this.esc(this.label('message.confirm_delete', { count: ids.length }))}</p>`,
            confirmClass: 'btn-danger',
            onConfirm: async () => {
                const response = await this.request(this.configValue.urls.delete, { method: 'POST', json: { ids } });

                if (!response.data?.blocked) {
                    return this.dialogError(this.errorOf(response));
                }

                const { deleted, blocked } = response.data;

                if (deleted.length > 0) {
                    this.alert('success', this.esc(this.label('message.deleted', { count: deleted.length })), true);
                }

                if (blocked.length > 0) {
                    this.alert('warning', `${this.esc(this.label('message.blocked'))}<ul class="mb-0 mt-2">${blocked.map((file) => `
                        <li><strong>${this.esc(file.name)}</strong> : ${file.usageList.map((usage) => (usage.editUrl
                            ? `<a href="${this.esc(usage.editUrl)}" target="_blank" rel="noopener">${this.esc(usage.label)} « ${this.esc(usage.title)} »</a>`
                            : `${this.esc(usage.label)} « ${this.esc(usage.title)} »`)).join(', ')}</li>`).join('')}</ul>`);
                }

                deleted.forEach((id) => this.selection.delete(id));

                if (deleted.includes(this.detailId)) {
                    this.closeDetail();
                }

                await this.load();

                return true;
            },
        });
    },

    merge({ params: { id } }) {
        const count = this.detailFile?.id === id ? this.detailFile.duplicates.length : 1;

        this.openDialog({
            title: this.label('field.duplicates'),
            body: `<p class="mb-0">${this.esc(this.label('message.confirm_merge', { count }))}</p>`,
            confirmClass: 'btn-warning',
            onConfirm: async () => {
                const response = await this.request(this.configValue.urls.merge, { method: 'POST', json: { keep: id } });

                if (!response.ok) {
                    return this.dialogError(this.errorOf(response));
                }

                this.alert('success', this.esc(this.label('message.merged', { count: response.data.updated })), true);
                await this.load();
                await this.openDetail(id);

                return true;
            },
        });
    },
};
