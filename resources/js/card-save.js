// Save button on ad cards (switches "favourites" + "ads.card_save").
// Cards are the same HTML for everyone (guest pages are cached), so the state comes from
// <meta name="card-save" content="1,2,3"> (the signed-in person's saved ads; empty for guests).
// Guests are sent to sign in. Without JavaScript the button stays hidden (save from the ad page).

function meta() {
    return document.querySelector('meta[name="card-save"]');
}

function savedIds(m) {
    return new Set((m.content || '').split(',').filter(Boolean));
}

// The words come once from <meta name="card-save-labels">, the ad title from the card itself (PR 112: smaller pages).
function label(button, on) {
    const words = document.querySelector('meta[name="card-save-labels"]');
    const title = button.closest('[data-card]')?.querySelector('[data-card-title]')?.textContent?.trim() || '';
    const base = (on ? words?.dataset.on : words?.dataset.off) || '';
    return title ? `${base}: ${title}` : base;
}

function paint(button, on) {
    button.setAttribute('aria-pressed', on ? 'true' : 'false');
    button.setAttribute('aria-label', label(button, on));
}

async function toggle(button, m) {
    const id = button.dataset.cardSave;
    if (!m.dataset.token) {
        // Guest: sign in first (or open the ad, where the Save button explains the rest).
        const link = button.closest('[data-card]')?.querySelector('a[href]');
        window.location.assign(m.dataset.login || link?.href || '/');
        return;
    }
    button.disabled = true;
    try {
        const res = await fetch(m.dataset.url.replace(/\/0$/, `/${id}`), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': m.dataset.token, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (res.status === 401 || res.status === 419) {
            window.location.reload(); // signed out or the page is too old: a fresh page shows the right state
            return;
        }
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            if (data.message) window.alert(data.message);
            return;
        }
        const ids = savedIds(m);
        if (data.saved === true) ids.add(id);
        else ids.delete(id);
        m.content = [...ids].join(',');
        // The same ad can be on the page twice (for example "Popular now" and "Latest ads").
        document.querySelectorAll(`button[data-card-save="${CSS.escape(id)}"]`).forEach((b) => paint(b, data.saved === true));
        document.dispatchEvent(new CustomEvent('favourite:changed', { detail: { saved: data.saved === true } }));
    } catch {
        // Offline: nothing changed; the button simply stays as it was.
    } finally {
        button.disabled = false;
    }
}

export function bindCardSave(root = document) {
    const m = meta();
    if (!m) return;
    const ids = savedIds(m);
    root.querySelectorAll('button[data-card-save]:not([data-bound])').forEach((button) => {
        button.dataset.bound = '1';
        paint(button, ids.has(button.dataset.cardSave));
        button.hidden = false;
        button.addEventListener('click', (e) => {
            e.preventDefault();
            toggle(button, meta() || m);
        });
    });
}

export function initCardSave() {
    bindCardSave();
    document.addEventListener('page:swapped', () => bindCardSave()); // the instant language switch puts a new page in
    document.addEventListener('cards:added', (e) => bindCardSave(e.target instanceof Element ? e.target : document));
}
