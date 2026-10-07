/*
 * gm-file-browser: Listing: query, grid, pagination and navigation.
 */
export default {
    async load() {
        this.gridTarget.innerHTML = `<div class="text-muted py-5 text-center gm-file-grid-message">${this.esc(this.label('message.loading'))}</div>`;
        const [sortBy, direction] = this.sortTarget.value.split('_');
        const params = new URLSearchParams({ path: this.path, page: this.page, sort: sortBy, direction });

        for (const [name, value] of Object.entries({
            search: this.searchTarget.value.trim(),
            type: this.typeTarget.value,
            from: this.fromTarget.value,
            to: this.toTarget.value,
            recursive: this.recursiveTarget.checked ? '1' : '',
            orphans: this.orphansTarget.checked ? '1' : '',
            duplicates: this.duplicatesTarget.checked ? '1' : '',
        })) {
            if (value) {
                params.set(name, value);
            }
        }

        // Picker: the mime types accepted by the field.
        (this.accept ?? []).forEach((pattern) => params.append('accept[]', pattern));

        // Only the latest listing is rendered: a slower previous one is aborted, or ignored when it lands.
        this.listingRequest?.abort();
        const request = new AbortController();
        this.listingRequest = request;
        const response = await this.request(`${this.configValue.urls.browse}?${params}`, { signal: request.signal });

        if (this.listingRequest !== request) {
            return;
        }

        if (!response.ok) {
            this.gridTarget.innerHTML = '';
            this.alert('danger', this.esc(this.errorOf(response)));

            if (response.status === 422 && this.path !== '') {
                this.navigate('');
            }

            return;
        }

        this.listing = response.data;
        this.files = new Map(response.data.files.map((file) => [file.id, file]));
        this.setChildren(this.path, response.data.directories);
        this.render();
    },

    render() {
        const { breadcrumb, directories, files, pagination } = this.listing;

        this.breadcrumbTarget.innerHTML = this.breadcrumbItems(breadcrumb);

        if (this.hasDeleteDirectoryButtonTarget) {
            this.deleteDirectoryButtonTarget.classList.toggle('d-none', this.path === '');
        }

        // Folders stay whatever the filters, to keep navigating.
        const tiles = [
            ...(pagination.page === 1 ? directories.map((directory) => this.directoryTile(directory)) : []),
            ...files.map((file) => this.fileTile(file)),
        ];

        this.gridTarget.innerHTML = tiles.length > 0
            ? tiles.join('')
            : `<div class="text-muted py-5 text-center gm-file-grid-message"><i class="bi bi-folder2-open d-block fs-1 mb-2 opacity-25"></i>${this.esc(this.label('message.empty'))}</div>`;
        this.totalTarget.textContent = this.label('message.total', { count: pagination.totalItems });
        this.paginationTarget.innerHTML = this.paginationLinks(pagination);

        this.renderTree();
        this.renderSelection();
    },

    directoryTile(directory) {
        return `
            <button type="button" class="gm-file-card gm-file-card-directory" title="${this.esc(directory.name)}"
                    data-action="gm-file-browser#open" data-gm-file-browser-path-param="${this.esc(directory.path)}">
                <span class="gm-file-card-preview"><i class="bi bi-folder-fill"></i></span>
                <span class="gm-file-card-body"><span class="gm-file-card-name">${this.esc(directory.name)}</span></span>
            </button>`;
    },

    fileTile(file) {
        const selected = this.selection.has(file.id);
        const check = this.configValue.canEdit && !this.isPicker() ? `
            <input type="checkbox" class="form-check-input gm-file-card-check" ${selected ? 'checked' : ''} aria-label="${this.esc(file.name)}"
                   data-action="gm-file-browser#toggleSelection" data-gm-file-browser-id-param="${file.id}">` : '';
        const usages = file.usages > 0
            ? `<span class="badge bg-success-subtle text-success" title="${this.esc(this.label('field.usages'))}"><i class="bi bi-link-45deg"></i> ${file.usages}</span>`
            : '';

        return `
            <div class="gm-file-card${selected ? ' is-selected' : ''}${this.detailId === file.id ? ' is-active' : ''}" data-file-id="${file.id}">
                ${check}
                <button type="button" class="gm-file-card-preview" title="${this.esc(file.name)}"
                        data-action="gm-file-browser#${this.isPicker() ? 'pickFile' : 'showFile'}" data-gm-file-browser-id-param="${file.id}">
                    ${this.preview(file)}
                </button>
                <span class="gm-file-card-body">
                    <span class="gm-file-card-name" title="${this.esc(file.name)}">${this.esc(file.name)}</span>
                    <span class="d-flex align-items-center justify-content-between gap-1 small text-muted">
                        <span>${this.esc(this.formatSize(file.size))}</span>${usages}
                    </span>
                </span>
            </div>`;
    },

    preview(file, large = false) {
        if (file.isImage) {
            const src = (large ? file.previewUrl : file.thumbnailUrl) ?? file.url;

            return `<img src="${this.esc(src)}" alt=""${large ? '' : ' loading="lazy"'}>`;
        }

        return `<i class="bi ${this.icon(file)}"></i><span class="gm-file-card-extension">${this.esc(file.extension)}</span>`;
    },

    // Same links as the core lists: first, previous, 2 pages around the current one, next, last.
    paginationLinks({ page, pages }) {
        if (pages <= 1) {
            return '';
        }

        const link = (target, content, { disabled = false, active = false, label = null } = {}) => `
            <li class="page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}"${active ? ' aria-current="page"' : ''}>
                <button type="button" class="page-link" ${disabled ? 'disabled' : ''}${label ? ` aria-label="${this.esc(this.label(label))}" title="${this.esc(this.label(label))}"` : ''}
                        data-action="gm-file-browser#goToPage" data-gm-file-browser-page-param="${target}">${content}</button>
            </li>`;
        const numbers = [];

        for (let number = Math.max(1, page - 2); number <= Math.min(pages, page + 2); number++) {
            numbers.push(link(number, number, { active: number === page }));
        }

        return `
            <ul class="pagination mb-0">
                ${link(1, '<i class="bi bi-chevron-double-left"></i>', { disabled: page === 1, label: 'action.first' })}
                ${link(page - 1, '<i class="bi bi-chevron-left"></i>', { disabled: page === 1, label: 'action.previous' })}
                ${numbers.join('')}
                ${link(page + 1, '<i class="bi bi-chevron-right"></i>', { disabled: page === pages, label: 'action.next' })}
                ${link(pages, '<i class="bi bi-chevron-double-right"></i>', { disabled: page === pages, label: 'action.last' })}
            </ul>`;
    },

    breadcrumbItems(breadcrumb) {
        return [{ name: this.label('root'), path: '' }, ...breadcrumb].map((item, index, items) => (
            index === items.length - 1
                ? `<li class="breadcrumb-item active" aria-current="page">${index === 0 ? '<i class="bi bi-house-door me-1"></i>' : ''}${this.esc(item.name)}</li>`
                : `<li class="breadcrumb-item"><a href="#" data-action="gm-file-browser#open:prevent" data-gm-file-browser-path-param="${this.esc(item.path)}">${index === 0 ? '<i class="bi bi-house-door me-1"></i>' : ''}${this.esc(item.name)}</a></li>`
        )).join('');
    },

    open({ params: { path } }) {
        this.navigate(path ?? '');
    },

    navigate(path) {
        this.alertsTarget.innerHTML = '';
        this.path = path;
        this.page = 1;
        this.selection.clear();
        this.closeDetail();
        this.load().then(() => this.ensureTree(path));
    },

    goToPage({ params: { page } }) {
        this.page = page;

        // Back to the top of the grid, the listing being replaced under the pagination.
        if (this.gridTarget.getBoundingClientRect().top < 0) {
            this.gridTarget.closest('.card').scrollIntoView({ block: 'start' });
        }

        this.load();
    },

    search() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => this.filter(), 300);
    },

    filter() {
        this.page = 1;
        this.load();
    },

    resetFilters() {
        this.clearFilters();
        window.bootstrap.Collapse.getOrCreateInstance(this.filtersTarget, { toggle: false }).hide();
        this.filter();
    },

    clearFilters() {
        this.searchTarget.value = '';
        this.typeTarget.value = '';
        this.fromTarget.value = '';
        this.toTarget.value = '';
        this.orphansTarget.checked = false;
        this.duplicatesTarget.checked = false;
        this.recursiveTarget.checked = false;
        this.sortTarget.selectedIndex = 0;
    },
};
