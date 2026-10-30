import { Controller } from '@hotwired/stimulus';

/*
 * Filters of the library browser: the form is submitted as they change, the search after a pause.
 */
export default class FileFiltersController extends Controller {
    disconnect() {
        clearTimeout(this.timeout);
    }

    search() {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => this.submit(), 300);
    }

    submit() {
        clearTimeout(this.timeout);
        this.element.requestSubmit();
    }
}
