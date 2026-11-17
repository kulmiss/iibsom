// A button with data-copy-from="#id" copies that field's text (e.g. a new app key).
export function initCopyText() {
    document.querySelectorAll('[data-copy-from]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const field = document.querySelector(btn.dataset.copyFrom);
            if (!field) return;
            try {
                await navigator.clipboard.writeText(field.value);
                btn.textContent = btn.dataset.done;
            } catch {
                field.select(); // no clipboard: select it so the user can copy by hand
            }
        });
    });
}
