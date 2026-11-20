// Place picker next to the home search (PR 87, switch "search.location_picker").
// Cities → districts with counts, a search box, and the choice is sent with the search as ?city=&area=.
// One set of listeners on the page, so it keeps working after the instant language switch.
const $ = (sel, root = document) => root.querySelector(sel);

function dialog() {
    return document.getElementById('place-picker');
}

function show(level) {
    const d = dialog();
    if (!d) return;
    d.querySelectorAll('[data-loc-level]').forEach((ul) => { ul.hidden = ul.dataset.locLevel !== level; });
    const back = $('[data-loc-back]', d);
    if (back) back.classList.toggle('hidden', level === 'cities');
    d.dataset.locShowing = level;
}

function startLevel() {
    return dialog()?.dataset.locStart || 'cities';
}

/** Ready the form: the hidden fields carry the choice; the simple city list (no-JavaScript fallback) is not sent. */
function bind() {
    const fallback = $('[data-loc-fallback]');
    if (fallback) fallback.disabled = true;
}

function pick(btn) {
    const city = $('[data-loc-city]');
    const area = $('[data-loc-area]');
    const label = $('[data-loc-label]');
    if (!city || !area) return;
    city.value = btn.dataset.city || '';
    area.value = btn.dataset.area || '';
    city.disabled = city.value === ''; // nothing chosen: keep the address clean
    area.disabled = area.value === '';
    if (label) label.textContent = btn.dataset.label || '';
    dialog()?.close();
    $('[data-loc-open]')?.focus();
}

function filter(input) {
    const d = dialog();
    const words = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    if (words.length === 0) {
        show(d?.dataset.locBefore || startLevel());
        return;
    }
    if (d.dataset.locShowing !== 'search') d.dataset.locBefore = d.dataset.locShowing || startLevel();
    show('search');
    let any = false;
    d.querySelectorAll('[data-loc-names]').forEach((li) => {
        const hit = words.every((w) => li.dataset.locNames.includes(w));
        li.hidden = !hit;
        any ||= hit;
    });
    const none = $('[data-loc-none]', d);
    if (none) none.hidden = any;
}

export function initPlacePicker() {
    bind();
    document.addEventListener('page:swapped', bind);

    document.addEventListener('click', (event) => {
        const t = event.target;
        if (!(t instanceof Element)) return;
        const d = dialog();
        if (!d) return;

        if (t.closest('[data-loc-open]')) {
            const search = $('[data-loc-search]', d);
            if (search) search.value = '';
            delete d.dataset.locBefore;
            show(startLevel());
            d.showModal();
            search?.focus();
            return;
        }
        if (!d.contains(t)) return;
        if (t === d || t.closest('[data-loc-close]')) { d.close(); return; } // backdrop or ×
        const go = t.closest('[data-loc-goto]');
        if (go) { show(go.dataset.locGoto); return; }
        if (t.closest('[data-loc-back]')) {
            const search = $('[data-loc-search]', d);
            if (search) search.value = '';
            show('cities');
            return;
        }
        const choice = t.closest('[data-loc-pick]');
        if (choice) pick(choice);
    });

    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-loc-search]')) filter(event.target);
    });
}
