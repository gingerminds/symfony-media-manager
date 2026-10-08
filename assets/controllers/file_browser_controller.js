import { Controller } from '@hotwired/stimulus';

import actions from '../file_browser/actions.js';
import detail from '../file_browser/detail.js';
import dialog from '../file_browser/dialog.js';
import directories from '../file_browser/directories.js';
import helpers from '../file_browser/helpers.js';
import listing from '../file_browser/listing.js';
import picker from '../file_browser/picker.js';
import selection from '../file_browser/selection.js';
import tree from '../file_browser/tree.js';
import uploads from '../file_browser/uploads.js';

/*
 * File library browser (components/file/_browser.html.twig): folder tree, filters, paginated grid,
 * detail panel, uploads (button or drag & drop) and file actions, over the JSON endpoints of
 * FileLibraryController. Writes send the `X-CSRF-Token` header.
 *
 * Values: mode (manager|picker), config (FileLibraryController::browserParameters()), labels.
 * The methods live in ../file_browser/, one module per concern.
 */
export default class FileBrowserController extends Controller {
    static targets = [
        'breadcrumb', 'tree', 'grid', 'pagination', 'total', 'detail', 'alerts', 'uploads', 'dropzone',
        'selectAll', 'selectionCount', 'selectionAction', 'fileActions', 'directoryActions', 'renameDirectoryButton', 'filters',
        'search', 'type', 'sort', 'recursive', 'orphans', 'duplicates', 'from', 'to',
        'dialog', 'dialogTitle', 'dialogBody', 'dialogConfirm', 'uploadInput', 'pickSummary', 'pickConfirm',
    ];

    static values = {
        mode: { type: String, default: 'manager' },
        config: Object,
        labels: Object,
    };

    connect() {
        this.path = this.configValue.startPath || '';
        this.page = 1;
        this.files = new Map();
        this.selection = new Set();
        this.directorySelection = new Set();
        this.nodes = new Map([['', { children: null }]]);
        this.expanded = { tree: new Set(['']), chooser: new Set(['']) };
        this.dragDepth = 0;
        this.picked = new Map();

        // The picker loads when a field opens it (startPicking).
        if (!this.isPicker()) {
            this.load().then(() => this.ensureTree(this.path));
        }
    }

    disconnect() {
        clearTimeout(this.searchTimeout);
        this.listingRequest?.abort();
    }
}

Object.assign(FileBrowserController.prototype, helpers, listing, tree, selection, detail, actions, directories, uploads, dialog, picker);
