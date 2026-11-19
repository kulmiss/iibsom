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

    // A button with data-copy-link inside [data-share] copies that page address (or opens the phone's share sheet).
    document.querySelectorAll('[data-share] [data-copy-link]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const box = btn.closest('[data-share]');
            if (navigator.share) {
                try { await navigator.share({ title: box.dataset.title, url: box.dataset.url }); return; } catch { /* closed */ }
            }
            try {
                await navigator.clipboard.writeText(box.dataset.url);
                btn.textContent = btn.dataset.done;
            } catch { /* no clipboard */ }
        });
    });
}
