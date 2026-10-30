import { Controller } from '@hotwired/stimulus';
import { showModal } from '../lib/modal.js';

/*
 * Dialog of the library browser (components/file/_dialog.html.twig), rendered by the server when the
 * URL holds `dialog`: moved to the body, then shown. In the picker modal it would stay under the
 * backdrop of its own (a modal is a stacking context), and the admin cards are transformed on hover,
 * which breaks a fixed modal. Its link and form target the browser frame from there.
 * Closed without submitting, it follows its cancel link to drop the dialog from the URL; submitted,
 * it closes while the frame reloads. Either way it is removed once hidden.
 */
export default class FileDialogController extends Controller {
    static targets = ['cancel', 'input'];

    connect() {
        if (this.element.parentElement !== document.body) {
            // Connects again from the body.
            document.body.append(this.element);

            return;
        }

        this.submitting = false;
        this.onShown = () => this.hasInputTarget && this.inputTarget.select();
        this.onHidden = () => {
            if (!this.submitting) {
                this.cancelTarget.click();
            }

            this.element.remove();
            // Bootstrap leaves the page scrollable, even with the picker modal still open under it.
            document.body.classList.toggle('modal-open', document.querySelector('.modal.show') !== null);
        };

        this.element.addEventListener('shown.bs.modal', this.onShown);
        this.element.addEventListener('hidden.bs.modal', this.onHidden);
        showModal(this.element);
    }

    disconnect() {
        if (!this.onHidden) {
            return;
        }

        this.element.removeEventListener('shown.bs.modal', this.onShown);
        this.element.removeEventListener('hidden.bs.modal', this.onHidden);
        this.onHidden = null;
        window.bootstrap.Modal.getInstance(this.element)?.dispose();
    }

    submitted() {
        this.submitting = true;
        window.bootstrap.Modal.getInstance(this.element)?.hide();
    }
}
