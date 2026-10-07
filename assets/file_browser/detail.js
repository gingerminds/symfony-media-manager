/*
 * gm-file-browser: Detail panel of a file.
 */
export default {
    async showFile({ params: { id } }) {
        await this.openDetail(id);
    },

    async openDetail(id) {
        this.detailId = id;
        this.gridTarget.querySelectorAll('[data-file-id]').forEach((card) => card.classList.toggle('is-active', card.dataset.fileId === id));
        this.detailTarget.classList.remove('d-none');
        this.detailTarget.innerHTML = `<div class="p-3 text-muted">${this.esc(this.label('message.loading'))}</div>`;
        const response = await this.request(this.fileUrl(id));

        if (!response.ok) {
            this.closeDetail();
            this.alert('danger', this.esc(this.errorOf(response)));

            return;
        }

        this.renderDetail(response.data);
    },

    renderDetail(file) {
        this.detailFile = file;
        const row = (label, value) => `<dt class="col-5 text-muted fw-normal">${this.esc(this.label(label))}</dt><dd class="col-7 text-break">${value}</dd>`;
        const usages = file.usageList.length > 0
            ? `<ul class="list-unstyled mb-0">${file.usageList.map((usage) => `
                <li class="mb-2">
                    <span class="text-muted small">${this.esc(usage.label)}</span><br>
                    ${usage.editUrl ? `<a href="${this.esc(usage.editUrl)}" target="_blank" rel="noopener">${this.esc(usage.title)} <i class="bi bi-box-arrow-up-right small"></i></a>` : this.esc(usage.title)}
                </li>`).join('')}</ul>`
            : `<p class="text-muted small mb-0">${this.esc(this.label('message.not_used'))}</p>`;
        const duplicates = file.duplicates.length > 0 ? `
            <ul class="list-unstyled small mb-3">${file.duplicates.map((duplicate) => `
                <li class="mb-1"><a href="#" data-action="gm-file-browser#showFile:prevent" data-gm-file-browser-id-param="${duplicate.id}">${this.esc(duplicate.name)}</a>
                    <span class="text-muted">— ${this.esc(duplicate.directory || this.label('root'))}</span></li>`).join('')}</ul>
            ${this.configValue.canEdit ? `<button type="button" class="btn btn-sm btn-outline-warning w-100" data-action="gm-file-browser#merge" data-gm-file-browser-id-param="${file.id}">
                <i class="bi bi-intersect me-1"></i> ${this.esc(this.label('action.merge'))}</button>` : ''}` : null;
        const section = (key, title, count, badge, body) => `
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2 px-0 fw-medium" type="button" data-bs-toggle="collapse" data-bs-target="#gm-file-detail-${key}" aria-expanded="false">
                        ${this.esc(this.label(title))} <span class="badge ${badge} ms-2">${count}</span>
                    </button>
                </h2>
                <div id="gm-file-detail-${key}" class="accordion-collapse collapse">
                    <div class="pb-3">${body}</div>
                </div>
            </div>`;
        const sections = `
            <div class="accordion accordion-flush mt-3 gm-file-detail-sections">
                ${section('usages', 'field.usages', file.usageList.length, 'bg-primary-subtle text-primary', usages)}
                ${duplicates === null ? '' : section('duplicates', 'field.duplicates', file.duplicates.length, 'bg-warning-subtle text-warning', duplicates)}
            </div>`;
        const action = (name, icon, label, style) => `
            <button type="button" class="btn btn-sm btn-${style}" title="${this.esc(this.label(label))}" aria-label="${this.esc(this.label(label))}"
                    data-action="gm-file-browser#${name}" data-gm-file-browser-id-param="${file.id}"><i class="bi ${icon}"></i></button>`;
        const actions = `
            <div class="d-flex gap-1 mb-3 gm-file-detail-actions">
                ${this.configValue.canEdit ? [
                    action('rename', 'bi-pencil', 'action.rename', 'outline-primary'),
                    action('moveFile', 'bi-folder-symlink', 'action.move', 'outline-info'),
                    action('deleteFile', 'bi-trash', 'action.delete', 'outline-danger ms-auto'),
                ].join('') : ''}
            </div>`;

        this.detailTarget.innerHTML = `
            <div class="p-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                    <h6 class="mb-0 text-break">${this.esc(file.name)}</h6>
                    <button type="button" class="btn-close" aria-label="${this.esc(this.label('action.close'))}" data-action="gm-file-browser#closeDetail"></button>
                </div>
                <a href="${this.esc(file.url)}" target="_blank" rel="noopener" class="gm-file-detail-preview mb-2" title="${this.esc(this.label('action.open'))}">
                    ${this.preview(file, true)}<span class="gm-file-detail-open"><i class="bi bi-box-arrow-up-right"></i></span>
                </a>
                ${actions}
                <dl class="row small mb-0">
                    ${row('field.type', this.esc(file.mimeType))}
                    ${row('field.size', this.esc(this.formatSize(file.size)))}
                    ${row('field.created_at', this.esc(this.formatDate(file.createdAt)))}
                    ${row('field.directory', this.esc(file.directory || this.label('root')))}
                </dl>
                ${sections}
            </div>`;
    },

    closeDetail() {
        this.detailId = null;
        this.detailFile = null;
        this.detailTarget.classList.add('d-none');
        this.detailTarget.innerHTML = '';
        this.gridTarget.querySelectorAll('.is-active').forEach((card) => card.classList.remove('is-active'));
    },
};
