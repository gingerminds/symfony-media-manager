import { Controller } from '@hotwired/stimulus';

/*
 * Uploads of the library browser (inside its Turbo frame), chosen or dropped: one request per file with
 * its progress, then the frame is reloaded and shows the messages. Dispatches
 * `gm-file-upload:uploaded` (detail: {file}) for each stored or known file (picker).
 */
export default class FileUploadController extends Controller {
    static targets = ['dropzone', 'progress'];

    static values = {
        url: String,
        reloadUrl: String,
        token: String,
        path: String,
        maxSize: Number,
        tooLarge: String,
    };

    connect() {
        this.depth = 0;
    }

    choose({ target }) {
        this.upload([...target.files]);
        target.value = '';
    }

    dragEnter(event) {
        if (this.isFileDrag(event)) {
            this.depth++;
            this.dropzoneTarget.classList.add('is-visible');
        }
    }

    dragOver(event) {
        if (this.isFileDrag(event)) {
            event.preventDefault();
        }
    }

    dragLeave(event) {
        if (this.isFileDrag(event) && --this.depth <= 0) {
            this.depth = 0;
            this.dropzoneTarget.classList.remove('is-visible');
        }
    }

    drop(event) {
        if (!this.isFileDrag(event)) {
            return;
        }

        event.preventDefault();
        this.depth = 0;
        this.dropzoneTarget.classList.remove('is-visible');
        this.upload([...event.dataTransfer.files]);
    }

    async upload(files) {
        const errors = [];

        for (const file of files) {
            if (file.size > this.maxSizeValue) {
                errors.push(this.tooLargeValue.replace('%name%', file.name));
                continue;
            }

            const progress = this.progressBar(file.name);
            const result = await this.send(file, (percent) => {
                progress.querySelector('.progress-bar').style.width = `${percent}%`;
            });
            progress.remove();

            if (result?.file) {
                this.dispatch('uploaded', { detail: { file: result.file } });
            }
        }

        this.reload(errors);
    }

    // The messages of the uploads are flashes, shown by the reloaded frame.
    reload(errors) {
        const frame = this.element.closest('turbo-frame');

        if (errors.length > 0) {
            frame.addEventListener('turbo:frame-load', () => this.showErrors(frame, errors), { once: true });
        }

        if (frame.src === this.reloadUrlValue) {
            frame.reload();
        } else {
            frame.src = this.reloadUrlValue;
        }
    }

    // In the reloaded frame: this controller is the one of the previous content.
    showErrors(frame, errors) {
        const container = frame.querySelector('[data-gm-file-upload-target="progress"]');

        errors.forEach((message) => {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = message;
            container?.append(alert);
        });
    }

    progressBar(name) {
        const element = document.createElement('div');
        element.className = 'mb-2 small';
        element.innerHTML = '<div class="text-truncate"></div><div class="progress" style="height: 4px"><div class="progress-bar" style="width: 0"></div></div>';
        element.firstElementChild.textContent = name;
        this.progressTarget.append(element);

        return element;
    }

    send(file, onProgress) {
        return new Promise((resolve) => {
            const data = new FormData();
            data.append('file', file);
            data.append('path', this.pathValue);
            data.append('_token', this.tokenValue);

            const request = new XMLHttpRequest();
            request.open('POST', this.urlValue);
            request.setRequestHeader('Accept', 'application/json');
            request.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    onProgress(Math.round((event.loaded / event.total) * 100));
                }
            });
            request.addEventListener('load', () => {
                try {
                    resolve(JSON.parse(request.responseText));
                } catch {
                    resolve(null);
                }
            });
            request.addEventListener('error', () => resolve(null));
            request.send(data);
        });
    }

    isFileDrag(event) {
        return [...(event.dataTransfer?.types ?? [])].includes('Files');
    }
}
