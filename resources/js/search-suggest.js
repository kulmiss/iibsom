// Search suggestions while typing (switch "search.suggestions").
// Works on every search box with data-suggest="<url>" (home search and the header search).
// - Empty box: the visitor's own recent searches, kept only on this phone (localStorage), with a Clear button.
// - 2+ letters: "word in Category" (when searching inside a category), matching categories, then popular words.
// The request sends no cookie and the server keeps nothing about the visitor.
// Keyboard: Up/Down to move, Enter to open, Escape to close. Without JavaScript the box is a normal search box.

const RECENT_KEY = 'iibsom-recent';
const RECENT_MAX = 6;
let counter = 0;

function readRecent() {
    try {
        const list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
        return Array.isArray(list) ? list.filter((t) => typeof t === 'string' && t.trim() !== '').slice(0, RECENT_MAX) : [];
    } catch {
        return []; // storage blocked or broken: just no recent searches
    }
}

function saveRecent(text) {
    const term = text.trim().slice(0, 100);
    if (term === '') return;
    const list = [term, ...readRecent().filter((t) => t.toLowerCase() !== term.toLowerCase())].slice(0, RECENT_MAX);
    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(list));
    } catch { /* storage blocked */ }
}

function clearRecent() {
    try {
        localStorage.removeItem(RECENT_KEY);
    } catch { /* storage blocked */ }
}

function texts() {
    const t = document.getElementById('suggest-text');
    const d = t ? t.dataset : {};
    return {
        recent: d.recent || 'Recent searches',
        clear: d.clear || 'Clear',
        category: d.category || 'Category',
        none: d.none || '',
        one: d.one || ':count',
        many: d.many || ':count',
    };
}

// The typed part in bold, the rest normal (text nodes only, never HTML from the server).
function highlighted(text, typed) {
    const span = document.createElement('span');
    span.className = 'min-w-0 flex-1 truncate';
    const at = typed ? text.toLowerCase().indexOf(typed.toLowerCase()) : -1;
    if (at < 0) {
        span.textContent = text;
        return span;
    }
    span.append(text.slice(0, at));
    const b = document.createElement('strong');
    b.textContent = text.slice(at, at + typed.length);
    span.append(b, text.slice(at + typed.length));
    return span;
}

function bindInput(input) {
    input.dataset.suggestBound = '1';
    const id = `suggest-${++counter}`;
    const box = input.parentElement;
    box.classList.add('relative');

    const list = document.createElement('ul');
    list.id = id;
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    list.className = 'absolute inset-x-0 top-full z-30 mt-1 max-h-80 overflow-y-auto rounded-lg bg-white py-1 text-start text-sm text-stone-900 shadow-lg ring-1 ring-stone-200';
    const live = document.createElement('span');
    live.className = 'sr-only';
    live.setAttribute('aria-live', 'polite');
    box.append(list, live);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', id);

    let items = [];
    let active = -1;
    let timer = 0;
    let controller = null;
    let asked = '';

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    }

    function setActive(i) {
        items.forEach((li, n) => {
            li.setAttribute('aria-selected', n === i ? 'true' : 'false');
            li.classList.toggle('bg-brand-50', n === i);
        });
        active = i;
        if (i >= 0) {
            input.setAttribute('aria-activedescendant', items[i].id);
            items[i].scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function go(li) {
        if (li.dataset.remember) saveRecent(li.dataset.remember);
        close();
        window.location.assign(li.dataset.url);
    }

    function option(label, url, opts = {}) {
        const li = document.createElement('li');
        li.id = `${id}-${items.length}`;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', 'false');
        li.className = 'flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-stone-100';
        li.dataset.url = url;
        if (opts.remember) li.dataset.remember = opts.remember;
        li.append(highlighted(label, opts.typed || ''));
        if (opts.tag) {
            const tag = document.createElement('span');
            tag.className = 'shrink-0 text-xs text-stone-500';
            tag.textContent = opts.tag;
            li.append(tag);
        }
        // mousedown keeps the focus in the box, so the list does not close before the click.
        li.addEventListener('mousedown', (e) => e.preventDefault());
        li.addEventListener('click', () => go(li));
        items.push(li);
        return li;
    }

    function open(nodes, count) {
        list.replaceChildren(...nodes);
        const t = texts();
        live.textContent = count === 0 ? t.none : (count === 1 ? t.one : t.many).replace(':count', String(count));
        if (nodes.length === 0) {
            close();
            return;
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        setActive(-1);
    }

    function searchUrl(text) {
        const form = input.form;
        const url = new URL(form ? form.action : window.location.href, window.location.origin);
        url.searchParams.set('q', text);
        return url.toString();
    }

    function showRecent() {
        items = [];
        const recent = readRecent();
        if (recent.length === 0) {
            close();
            return;
        }
        const t = texts();
        const head = document.createElement('li');
        head.setAttribute('role', 'presentation');
        head.className = 'flex items-center justify-between px-3 pb-1 pt-1.5 text-xs font-semibold text-stone-500';
        const title = document.createElement('span');
        title.textContent = t.recent;
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'rounded px-1.5 py-0.5 font-semibold text-brand-700 hover:bg-stone-100';
        clear.textContent = t.clear;
        clear.addEventListener('mousedown', (e) => e.preventDefault());
        clear.addEventListener('click', () => {
            clearRecent();
            close();
            input.focus();
        });
        head.append(title, clear);
        const nodes = recent.map((r) => option(r, searchUrl(r), { remember: r }));
        open([head, ...nodes], nodes.length);
    }

    async function ask(typed) {
        if (controller) controller.abort();
        controller = new AbortController();
        asked = typed;
        const url = new URL(input.dataset.suggest, window.location.origin);
        url.searchParams.set('q', typed);
        if (input.dataset.scope) url.searchParams.set('in', input.dataset.scope);
        try {
            const res = await fetch(url, { credentials: 'omit', headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!res.ok) return;
            const data = await res.json();
            if (asked !== typed || input.value.trim() !== typed) return; // the visitor typed more meanwhile
            items = [];
            const t = texts();
            const nodes = [];
            if (data.scope) nodes.push(option(data.scope.text, data.scope.url, { remember: typed, typed }));
            (data.categories || []).forEach((c) => nodes.push(option(c.name, c.url, { tag: t.category, typed })));
            (data.terms || []).forEach((w) => nodes.push(option(w.text, searchUrl(w.text), { remember: w.text, typed })));
            open(nodes, nodes.length);
        } catch {
            // Offline, aborted or blocked: the box still works as a normal search box.
        }
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const typed = input.value.trim();
        if (typed.length < 2) {
            if (controller) controller.abort();
            asked = '';
            if (typed === '') showRecent();
            else close();
            return;
        }
        timer = setTimeout(() => ask(typed), 150);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim() === '') showRecent();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (!list.hidden) {
                e.preventDefault();
                close();
            }
            return;
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (list.hidden) {
                if (input.value.trim() === '') showRecent();
                return;
            }
            e.preventDefault();
            const n = items.length;
            if (n === 0) return;
            setActive(e.key === 'ArrowDown' ? (active + 1) % n : (active <= 0 ? n - 1 : active - 1));
            return;
        }
        if (e.key === 'Enter' && !list.hidden && active >= 0) {
            e.preventDefault();
            go(items[active]);
        }
    });

    input.addEventListener('blur', () => setTimeout(close, 100));

    if (input.form) {
        input.form.addEventListener('submit', () => saveRecent(input.value));
    }
}

function bind() {
    document.querySelectorAll('input[data-suggest]:not([data-suggest-bound])').forEach(bindInput);
    // Phone header search: opening the magnifier puts the cursor in the box.
    document.querySelectorAll('details[data-header-search]:not([data-bound])').forEach((details) => {
        details.dataset.bound = '1';
        details.addEventListener('toggle', () => {
            if (details.open) details.querySelector('input')?.focus();
        });
    });
}

export function initSearchSuggest() {
    bind();
    document.addEventListener('page:swapped', bind); // the instant language switch puts a new page in
}
