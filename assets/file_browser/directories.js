/*
 * gm-file-browser: Actions on the selected folders (rename, move, delete) and the folder chooser of the move dialogs.
 */
export default {
    renameDirectory() {
        const [path] = this.directorySelection;

        if (path === undefined) {
            return;
        }

        this.openDialog({
            title: this.label('action.rename'),
            body: this.inputField('field.directory_name', path.split('/').pop()),
            onConfirm: async () => {
                const response = await this.request(this.configValue.urls.directory, {
                    method: 'PATCH',
                    json: { paths: [path], parent: this.parentOf(path), name: this.dialogInput().value },
                });

                return this.afterDirectoryMove(response, [path], (moved) => this.label('message.directory_renamed', { name: moved[0].name }));
            },
        });
    },

    moveDirectories() {
        const paths = [...this.directorySelection];

        this.openChooser({
            title: this.label('action.move'),
            excluded: paths,
            summary: (target) => this.label('message.move_directories_to', { count: paths.length, path: target || this.label('root') }),
            onConfirm: async (target) => {
                const response = await this.request(this.configValue.urls.directory, { method: 'PATCH', json: { paths, parent: target } });

                return this.afterDirectoryMove(response, paths, (moved) => this.label('message.directories_moved', { count: moved.length, path: target || this.label('root') }));
            },
        });
    },

    async afterDirectoryMove(response, paths, message) {
        if (!response.ok) {
            return this.dialogError(this.errorOf(response));
        }

        this.forgetDirectories(paths);
        response.data.forEach((directory) => (this.node(this.parentOf(directory.path)).children = null));
        this.alert('success', this.esc(message(response.data)), true);
        await this.reloadDirectory();

        return true;
    },

    deleteDirectories() {
        const paths = [...this.directorySelection];

        this.openDialog({
            title: this.label('action.delete'),
            body: `<p class="mb-0">${this.esc(this.label('message.confirm_delete_directories', { count: paths.length }))}</p>`,
            confirmClass: 'btn-danger',
            onConfirm: async () => {
                const response = await this.request(this.configValue.urls.directory, { method: 'DELETE', json: { paths } });

                if (!response.data?.kept) {
                    return this.dialogError(this.errorOf(response));
                }

                const { deleted, kept } = response.data;

                if (deleted.length > 0) {
                    this.alert('success', this.esc(this.label('message.directories_deleted', { count: deleted.length })), true);
                }

                if (kept.length > 0) {
                    this.alert('warning', `${this.esc(this.label('message.directories_kept'))}<ul class="mb-0 mt-2">${kept.map((path) => `<li>${this.esc(path)}</li>`).join('')}</ul>`);
                }

                this.forgetDirectories(deleted);
                await this.reloadDirectory();

                return true;
            },
        });
    },

    forgetDirectories(paths) {
        [...this.nodes.keys()]
            .filter((node) => paths.some((path) => node === path || node.startsWith(`${path}/`)))
            .forEach((node) => this.nodes.delete(node));
    },

    async reloadDirectory() {
        this.directorySelection.clear();
        this.node(this.path).children = null;
        await this.load();
        await this.ensureTree(this.path);
    },

    parentOf(path) {
        return path.split('/').slice(0, -1).join('/');
    },

    // Move dialog with the folder tree; the `excluded` folders and their subfolders cannot be chosen.
    async openChooser({ title, summary, onConfirm, excluded = [] }) {
        this.chooserPath = this.path;
        this.chooserExcluded = excluded;
        this.chooserSummary = summary;
        this.expanded.chooser = new Set(['']);
        this.openDialog({
            title,
            body: '<div data-chooser></div>',
            confirmLabel: this.label('action.move_here'),
            onConfirm: () => onConfirm(this.chooserPath),
        });
        await this.ensureTree(this.chooserPath, 'chooser');
    },

    chooserOpen({ params: { path } }) {
        this.chooserPath = path ?? '';
        this.renderChooser();
    },

    renderChooser() {
        const container = this.dialogBodyTarget.querySelector('[data-chooser]');

        if (!container) {
            return;
        }

        const path = this.chooserPath;
        container.innerHTML = `
            <ul class="list-unstyled mb-0 p-2 border rounded gm-file-chooser">${this.treeItem('', this.label('root'), 'chooser', path, 'chooserOpen')}</ul>
            <p class="mt-3 mb-0">${this.esc(this.chooserSummary(path))}</p>`;
        this.revealActiveFolder(container.querySelector('.gm-file-chooser'));
    },

    isExcluded(path, scope) {
        return scope === 'chooser' && (this.chooserExcluded ?? []).some((excluded) => path === excluded || path.startsWith(`${excluded}/`));
    },
};
