/**
 * "Recommended for you" + "Recently viewed" (decision #17).
 * What you looked at is kept ONLY in this browser (localStorage). Nothing is
 * saved on the server; the home page asks for matching ads with the list of
 * category numbers and ad numbers, and shows them. "Clear" deletes it all.
 */
const KEY = 'iibsom-history';
const MAX_ADS = 30;
const MAX_CATS = 20;

function read() {
    try {
        const h = JSON.parse(localStorage.getItem(KEY) || 'null');
        if (h && Array.isArray(h.ads) && h.cats && typeof h.cats === 'object') return h;
    } catch { /* blocked or broken */ }
    return { ads: [], cats: {} };
}

function write(h) {
    try { localStorage.setItem(KEY, JSON.stringify(h)); } catch { /* full or blocked */ }
}

function track() {
    const el = document.querySelector('[data-track-cat]');
    if (!el) return;
    const h = read();
    const cat = String(Number(el.dataset.trackCat));
    const ad = Number(el.dataset.trackAd || 0);

    h.cats[cat] = (h.cats[cat] || 0) + (ad ? 2 : 1); // looking at an ad counts more than a list
    const top = Object.entries(h.cats).sort((a, b) => b[1] - a[1]).slice(0, MAX_CATS);
    h.cats = Object.fromEntries(top);

    if (ad) h.ads = [ad, ...h.ads.filter((id) => id !== ad)].slice(0, MAX_ADS);
    write(h);
}

async function fill() {
    const box = document.querySelector('[data-for-you]');
    if (!box) return;
    const h = read();
    const cats = Object.entries(h.cats).sort((a, b) => b[1] - a[1]).slice(0, 5).map(([c]) => c);
    if (!cats.length && !h.ads.length) return;

    const params = new URLSearchParams();
    cats.forEach((c) => params.append('c[]', c));
    h.ads.slice(0, 12).forEach((a) => params.append('v[]', String(a)));

    try {
        const res = await fetch(`${box.dataset.url}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'omit' });
        if (!res.ok) return;
        const data = await res.json();
        let shown = false;
        for (const part of ['recommended', 'recent']) {
            const slot = box.querySelector(`[data-slot="${part}"]`);
            if (slot && data[part]) {
                slot.querySelector('[data-cards]').innerHTML = data[part];
                slot.hidden = false;
                shown = true;
            }
        }
        box.hidden = !shown;
    } catch { /* offline: just skip */ }

    box.querySelector('[data-clear-history]')?.addEventListener('click', () => {
        try { localStorage.removeItem(KEY); } catch { /* ignore */ }
        box.hidden = true;
    }, { once: true });
}

export function initForYou() {
    track();
    fill();
    document.addEventListener('page:swapped', fill);
}
