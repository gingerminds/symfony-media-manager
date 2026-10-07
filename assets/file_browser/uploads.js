/*
 * gm-file-browser: Uploads, by the file input or drag & drop, with progress.
 */
export default {
    chooseFiles({ target }) {
        this.upload([...target.files]);
        target.value = '';
    },

    dragEnter(event) {
        if (!this.acceptsDrop(event)) {
            return;
        }

        this.dragDepth++;
        this.dropzoneTarget.classList.add('is-visible');
    },

    dragOver(event) {
        if (this.acceptsDrop(event)) {
            event.preventDefault();
        }
    },

    dragLeave(event) {
        if (!this.acceptsDrop(event)) {
            return;
        }

        this.dragDepth = Math.max(0, this.dragDepth - 1);

        if (this.dragDepth === 0) {
            this.dropzoneTarget.classList.remove('is-visible');
        }
    },

    drop(event) {
        if (!this.acceptsDrop(event)) {
            return;
        }

        event.preventDefault();
        this.dragDepth = 0;
        this.dropzoneTarget.classList.remove('is-visible');
        this.upload([...event.dataTransfer.files]);
    },

    acceptsDrop(event) {
        return this.configValue.canEdit && [...(event.dataTransfer?.types ?? [])].includes('Files');
    },

    async upload(files) {
        const path = this.path;
        let uploaded = false;

        for (const file of files) {
            if (file.size > this.configValue.maxUploadSize) {
                this.alert('danger', this.esc(this.label('message.too_large', { name: file.name, max: this.formatSize(this.configValue.maxUploadSize) })));
                continue;
            }

            const progress = document.createElement('div');
            progress.className = 'mb-2 small';
            progress.innerHTML = `
                <div class="d-flex justify-content-between"><span class="text-truncate">${this.esc(file.name)}</span><span data-percent>0 %</span></div>
                <div class="progress" style="height: 4px"><div class="progress-bar" style="width: 0"></div></div>`;
            this.uploadsTarget.append(progress);

            const response = await this.send(file, path, (percent) => {
                progress.querySelector('.progress-bar').style.width = `${percent}%`;
                progress.querySelector('[data-percent]').textContent = `${percent} %`;
            });
            progress.remove();

            if (!response.ok) {
                this.alert('danger', `<strong>${this.esc(file.name)}</strong> : ${this.esc(this.errorOf(response))}`);
            } else if (response.data.duplicate) {
                const existing = response.data.file;
                this.alert('info', `${this.esc(this.label('message.duplicate', { name: file.name, existing: existing.directory ? `${existing.directory}/${existing.name}` : existing.name }))}
                    <button type="button" class="btn btn-sm btn-link p-0 align-baseline" data-action="gm-file-browser#showExisting"
                            data-gm-file-browser-id-param="${existing.id}" data-gm-file-browser-path-param="${this.esc(existing.directory)}">${this.esc(this.label('action.show'))}</button>`);
            } else {
                uploaded = true;
                this.alert('success', this.esc(this.label('message.uploaded', { name: file.name })), true);
            }
        }

        if (uploaded && path === this.path) {
            await this.load();
        }
    },

    send(file, path, onProgress) {
        return new Promise((resolve) => {
            const data = new FormData();
            data.append('file', file);
            data.append('path', path);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.configValue.urls.upload);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-Token', this.configValue.csrfToken);
            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    onProgress(Math.round((event.loaded / event.total) * 100));
                }
            });
            xhr.addEventListener('load', () => {
                let json = null;

                try {
                    json = JSON.parse(xhr.responseText);
                } catch {
                    // Not JSON: generic error.
                }

                resolve({ ok: xhr.status >= 200 && xhr.status < 300 && json !== null, status: xhr.status, data: json });
            });
            xhr.addEventListener('error', () => resolve({ ok: false, status: 0, data: null }));
            xhr.send(data);
        });
    },

    async showExisting({ params: { id, path } }) {
        if ((path ?? '') !== this.path) {
            this.navigate(path ?? '');
        }

        await this.openDetail(id);
    },
};
