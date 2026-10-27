/*
 * Opens a page-level modal (file library, media picker). Over an open modal (a form in a modal),
 * it goes above it with its backdrop: Bootstrap gives every modal the same z-index, the modal
 * under would show through and stay bright.
 */
const STEP = 10;

export function showModal(element) {
    const open = [...document.querySelectorAll('.modal.show')].filter((modal) => modal !== element).length;
    const modal = window.bootstrap.Modal.getOrCreateInstance(element);

    element.style.zIndex = open > 0 ? String(1055 + open * STEP) : '';
    modal.show();

    // The backdrop of this modal: the last one, appended by show(). Bootstrap reuses it: reset each time.
    const backdrop = [...document.querySelectorAll('.modal-backdrop')].at(-1);

    if (backdrop) {
        backdrop.style.zIndex = open > 0 ? String(1050 + open * STEP) : '';
    }
}
