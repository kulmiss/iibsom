// One listener on the page (PR 84), so the buttons keep working after the instant language switch puts a new page in.
export function initCopyText() {
    document.addEventListener('click', async (event) => {
        // A button with data-copy-from="#id" copies that field's text (e.g. a new app key).
        const fromBtn = event.target.closest('[data-copy-from]');
        if (fromBtn) {
            const field = document.querySelector(fromBtn.dataset.copyFrom);
            if (!field) return;
            try {
                await navigator.clipboard.writeText(field.value);
                fromBtn.textContent = fromBtn.dataset.done;
            } catch {
                field.select(); // no clipboard: select it so the user can copy by hand
            }
            return;
        }

        // An icon button with data-share-sheet (the phone top bar on the ad page): the phone's share sheet, or copy
        // the address and say so in its hidden status text (the icon stays).
        const sheetBtn = event.target.closest('[data-share-sheet]');
        if (sheetBtn) {
            if (navigator.share) {
                try { await navigator.share({ title: sheetBtn.dataset.title, url: sheetBtn.dataset.url }); return; } catch { /* closed */ }
            }
            try {
                await navigator.clipboard.writeText(sheetBtn.dataset.url);
                const done = sheetBtn.querySelector('[data-share-done]');
                if (done) done.textContent = sheetBtn.dataset.done;
                sheetBtn.title = sheetBtn.dataset.done;
            } catch { /* no clipboard */ }
            return;
        }

        // A button with data-copy-link inside [data-share] copies that page address (or opens the phone's share sheet).
        const linkBtn = event.target.closest('[data-share] [data-copy-link]');
        if (!linkBtn) return;
        const box = linkBtn.closest('[data-share]');
        if (navigator.share) {
            try { await navigator.share({ title: box.dataset.title, url: box.dataset.url }); return; } catch { /* closed */ }
        }
        try {
            await navigator.clipboard.writeText(box.dataset.url);
            linkBtn.textContent = linkBtn.dataset.done;
        } catch { /* no clipboard */ }
    });
}
