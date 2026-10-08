/*
 * gm-file-browser: Folder tree, shared by the side panel (`tree` scope) and the move dialog (`chooser` scope):
 * the subdirectories are cached once, each scope keeps its own expanded folders.
 */
export default {
    node(path) {
        if (!this.nodes.has(path)) {
            this.nodes.set(path, { children: null });
        }

        return this.nodes.get(path);
    },

    async loadChildren(path) {
        const response = await this.request(`${this.configValue.urls.directories}?${new URLSearchParams({ path })}`);
        this.setChildren(path, response.ok ? response.data : []);
    },

    // Children without subdirectory are known as such: no caret for them.
    setChildren(path, directories) {
        this.node(path).children = directories.map((directory) => directory.path);
        directories.forEach((directory) => {
            const child = this.node(directory.path);

            if (directory.hasChildren === false) {
                child.children = [];
            } else if (child.children?.length === 0) {
                child.children = null;
            }
        });
    },

    async ensureTree(path, scope = 'tree') {
        const segments = path.split('/').filter(Boolean);

        for (let index = 0; index <= segments.length; index++) {
            const ancestor = segments.slice(0, index).join('/');
            this.expanded[scope].add(ancestor);

            if (this.node(ancestor).children === null) {
                await this.loadChildren(ancestor);
            }
        }

        this.renderScope(scope);
    },

    async toggleNode({ params: { path, scope } }) {
        const expanded = this.expanded[scope];

        if (expanded.has(path)) {
            expanded.delete(path);
        } else {
            expanded.add(path);

            if (this.node(path).children === null) {
                await this.loadChildren(path);
            }
        }

        this.renderScope(scope);
    },

    renderScope(scope) {
        if (scope === 'chooser') {
            this.renderChooser();
        } else {
            this.renderTree();
        }
    },

    renderTree() {
        this.treeTarget.innerHTML = this.treeItem('', this.label('root'), 'tree', this.path, 'open');
        this.revealActiveFolder(this.treeTarget);
    },

    // Scrolls the tree (not the page) to the current folder when it is out of view.
    revealActiveFolder(container) {
        const item = container.querySelector('.gm-file-tree-item.is-active');

        if (!item) {
            return;
        }

        const top = item.getBoundingClientRect().top - container.getBoundingClientRect().top + container.scrollTop;

        if (top < container.scrollTop || top + item.offsetHeight > container.scrollTop + container.clientHeight) {
            container.scrollTop = top - container.clientHeight / 2;
        }
    },

    treeItem(path, name, scope, current, action) {
        if (this.isExcluded(path, scope)) {
            return `
                <li>
                    <div class="gm-file-tree-item d-flex align-items-center text-muted opacity-50">
                        <span class="gm-file-tree-caret"></span>
                        <span class="flex-grow-1 text-truncate" title="${this.esc(name)}"><i class="bi bi-folder me-1"></i>${this.esc(name)}</span>
                    </div>
                </li>`;
        }

        const node = this.node(path);
        const expanded = this.expanded[scope].has(path);
        const hasChildren = node.children === null || node.children.length > 0;
        const active = path === current;
        const caret = hasChildren
            ? `<button type="button" class="btn btn-link btn-sm p-0 gm-file-tree-caret" aria-expanded="${expanded}"
                       data-action="gm-file-browser#toggleNode" data-gm-file-browser-path-param="${this.esc(path)}" data-gm-file-browser-scope-param="${scope}">
                   <i class="bi bi-chevron-${expanded ? 'down' : 'right'}"></i>
               </button>`
            : '<span class="gm-file-tree-caret"></span>';
        const children = expanded && node.children?.length
            ? `<ul class="list-unstyled mb-0 gm-file-tree-children">${node.children.map((child) => this.treeItem(child, child.split('/').pop(), scope, current, action)).join('')}</ul>`
            : '';

        return `
            <li>
                <div class="gm-file-tree-item d-flex align-items-center${active ? ' is-active' : ''}">
                    ${caret}
                    <a href="#" class="flex-grow-1 text-truncate" title="${this.esc(name)}" ${active ? 'aria-current="true"' : ''}
                       data-action="gm-file-browser#${action}:prevent" data-gm-file-browser-path-param="${this.esc(path)}">
                        <i class="bi bi-${path === '' ? 'house-door' : `folder${active ? '2-open' : ''}`} me-1"></i>${this.esc(name)}
                    </a>
                </div>
                ${children}
            </li>`;
    },

    async refreshDirectory(path) {
        this.node(path).children = null;
        await this.ensureTree(path);
    },
};
