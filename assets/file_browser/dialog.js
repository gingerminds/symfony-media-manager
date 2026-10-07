/*
 * gm-file-browser: Confirmation / input dialog.
 */
export default {
    openDialog({ title, body, confirmLabel = null, confirmClass = 'btn-primary', onConfirm }) {
        this.dialogTitleTarget.textContent = title;
        this.dialogBodyTarget.innerHTML = body;
        this.dialogConfirmTarget.textContent = confirmLabel ?? this.label('action.confirm');
        this.dialogConfirmTarget.className = `btn ${confirmClass}`;
        this.dialogConfirmTarget.disabled = false;
        this.dialogHandler = onConfirm;

        const modal = window.bootstrap.Modal.getOrCreateInstance(this.dialogTarget);
        this.dialogTarget.addEventListener('shown.bs.modal', () => this.dialogInput()?.select(), { once: true });
        modal.show();
    },

    async confirmDialog() {
        if (!this.dialogHandler || this.dialogConfirmTarget.disabled) {
            return;
        }

        this.dialogConfirmTarget.disabled = true;

        try {
            if (await this.dialogHandler()) {
                window.bootstrap.Modal.getOrCreateInstance(this.dialogTarget).hide();
            }
        } finally {
            this.dialogConfirmTarget.disabled = false;
        }
    },

    inputField(label, value, suffix = '') {
        return `
            <label class="form-label" for="gm-file-browser-input">${this.esc(this.label(label))}</label>
            <div class="input-group has-validation">
                <input type="text" class="form-control" id="gm-file-browser-input" value="${this.esc(value)}" required>
                ${suffix ? `<span class="input-group-text">${this.esc(suffix)}</span>` : ''}
                <div class="invalid-feedback"></div>
            </div>`;
    },

    dialogInput() {
        return this.dialogBodyTarget.querySelector('input');
    },

    dialogError(message) {
        let feedback = this.dialogBodyTarget.querySelector('.invalid-feedback, [data-dialog-error]');

        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'alert alert-danger mt-3 mb-0';
            feedback.dataset.dialogError = '';
            this.dialogBodyTarget.append(feedback);
        }

        this.dialogInput()?.classList.add('is-invalid');
        feedback.classList.add('d-block');
        feedback.textContent = message;

        return false;
    },
};
