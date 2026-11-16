/**
 * Ad page: show the seller's number only after a tap (anti-bot),
 * copy/share link, tap a photo to zoom.
 */

const contact = document.querySelector('[data-contact]');
contact?.querySelector('[data-reveal]')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
        const res = await fetch(contact.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(String(res.status));
        const data = await res.json();

        const number = contact.querySelector('[data-number]');
        number.textContent = data.display;
        number.classList.remove('hidden');
        btn.classList.add('hidden');

        const buttons = contact.querySelector('[data-buttons]');
        buttons.classList.remove('hidden');
        buttons.classList.add('grid');
        const wa = contact.querySelector('[data-whatsapp]');
        const call = contact.querySelector('[data-call]');
        if (wa && data.whatsapp) { wa.href = data.whatsapp; wa.classList.replace('hidden', 'flex'); }
        if (call && data.call) { call.href = data.call; call.classList.replace('hidden', 'flex'); }
    } catch {
        btn.disabled = false;
    }
});

const share = document.querySelector('[data-share]');
share?.querySelector('[data-copy]')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const url = share.dataset.url;
    if (navigator.share) {
        try { await navigator.share({ title: share.dataset.title, url }); return; } catch { /* closed */ }
    }
    try {
        await navigator.clipboard.writeText(url);
        btn.textContent = btn.dataset.done;
    } catch { /* no clipboard */ }
});

const dialog = document.querySelector('[data-zoom-dialog]');
document.querySelectorAll('[data-zoom]').forEach((el) => {
    el.addEventListener('click', () => {
        if (!dialog?.showModal) { window.open(el.dataset.zoom, '_blank'); return; }
        dialog.querySelector('[data-zoom-img]').src = el.dataset.zoom;
        dialog.showModal();
    });
});
dialog?.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
