// Category / search pages: "Show 120 results" while filters change (the sheet on phones),
// and grid or list view (kept on this phone only). Everything also works without JavaScript.
const VIEW_KEY = 'iibsom-view';

function initCount(form) {
    const button = form.querySelector('[data-count-text]');
    if (!button || !form.dataset.countUrl) return;
    let timer;
    let asked = 0;
    const update = async () => {
        const params = new URLSearchParams(new FormData(form));
        for (const [key, value] of [...params]) if (value === '') params.delete(key);
        const url = new URL(form.dataset.countUrl, location.href);
        params.forEach((value, key) => url.searchParams.append(key, value));
        const mine = ++asked;
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return; // keep the old number; the button still works
            const data = await res.json();
            if (mine === asked) button.textContent = data.text;
        } catch { /* offline: keep the old number */ }
    };
    const later = () => { clearTimeout(timer); timer = setTimeout(update, 350); };
    form.addEventListener('change', later);
    form.addEventListener('input', (e) => { if (e.target.type === 'number' || e.target.type === 'search') later(); });
}

function initView() {
    const results = document.querySelector('[data-results]');
    const toggle = document.querySelector('[data-view-toggle]');
    if (!results || !toggle) return;
    const set = (view) => {
        results.dataset.view = view;
        toggle.querySelectorAll('[data-view]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.view === view)));
    };
    let saved = 'grid';
    try { saved = localStorage.getItem(VIEW_KEY) === 'list' ? 'list' : 'grid'; } catch { /* storage blocked */ }
    set(saved);
    toggle.addEventListener('click', (e) => {
        const button = e.target.closest('[data-view]');
        if (!button) return;
        set(button.dataset.view);
        try { localStorage.setItem(VIEW_KEY, button.dataset.view); } catch { /* storage blocked */ }
    });
}

// Short, clean links: leave out empty boxes ("?q=&min=&max=" helps nobody and splits the page cache).
function initSubmit(form) {
    form.addEventListener('submit', (event) => {
        if (form.method.toLowerCase() !== 'get') return;
        event.preventDefault();
        const url = new URL(form.action || location.href, location.href);
        url.search = '';
        for (const [key, value] of new FormData(form)) if (value !== '') url.searchParams.append(key, value);
        location.assign(url.toString());
    });
}

function start() {
    const form = document.querySelector('[data-filter-form]');
    if (form) { initCount(form); initSubmit(form); }
    initView();
}

// Quick filter drop-downs (switch "search.quick_bar"): one open at a time; a tap outside or Escape closes them.
function closeQuick(except) {
    document.querySelectorAll('[data-quick][open]').forEach((d) => { if (d !== except) d.open = false; });
}
document.addEventListener('toggle', (e) => {
    if (e.target.matches?.('[data-quick]') && e.target.open) closeQuick(e.target);
}, true);
document.addEventListener('click', (e) => { if (!e.target.closest('[data-quick]')) closeQuick(null); });
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = document.querySelector('[data-quick][open]');
    if (open) { open.open = false; open.querySelector('summary')?.focus(); }
});

// Leave empty boxes out of the address (?max=9000, not ?min=&max=9000).
document.addEventListener('submit', (e) => {
    if (!e.target.closest('[data-quick]')) return;
    e.target.querySelectorAll('input').forEach((i) => { if (i.value === '') i.disabled = true; });
});

export function initFilters() {
    start();
    document.addEventListener('page:swapped', start); // the language switch puts a new page in
}
