// "Show more ads" under lists of ads (switch "lists.show_more").
// Loads the next page (the same address Google and the page links use) and adds its ads to this list.
// The page links stay in the page for Google and for phones without JavaScript; with JavaScript the
// button takes their place. If anything goes wrong, the button simply opens the next page.

function nextLink(scope) {
    return scope.querySelector('[data-pages] a[rel="next"]');
}

function bind() {
    document.querySelectorAll('[data-show-more]:not([data-bound])').forEach((wrap) => {
        wrap.dataset.bound = '1';
        const list = document.querySelector('[data-results]');
        const pages = document.querySelector('[data-pages]');
        const button = wrap.querySelector('button');
        const live = wrap.querySelector('[aria-live]');
        if (!list || !pages || !button || !nextLink(document)) return;

        pages.hidden = true;
        wrap.hidden = false;

        button.addEventListener('click', async () => {
            const link = nextLink(document);
            if (!link) return;
            const url = link.href;
            button.disabled = true;
            button.textContent = button.dataset.loading;
            try {
                const res = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
                if (!res.ok) throw new Error(String(res.status));
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const cards = [...(doc.querySelector('[data-results]')?.children || [])];
                const newPages = doc.querySelector('[data-pages]');
                if (cards.length === 0 || !newPages) throw new Error('no ads');

                const first = document.importNode(cards[0], true);
                list.append(first, ...cards.slice(1).map((c) => document.importNode(c, true)));
                list.dispatchEvent(new Event('cards:added', { bubbles: true })); // Save buttons on the new cards
                pages.replaceChildren(...[...newPages.childNodes].map((n) => document.importNode(n, true)));
                live.textContent = cards.length === 1 ? button.dataset.loadedOne : button.dataset.loaded.replace(':count', String(cards.length));
                first.querySelector('a[href]')?.focus({ preventScroll: true });

                if (!nextLink(document)) wrap.hidden = true; // that was the last page
            } catch {
                window.location.assign(url); // fall back to the normal next page
                return;
            } finally {
                button.disabled = false;
                button.textContent = button.dataset.text;
            }
        });
    });
}

export function initShowMore() {
    bind();
    document.addEventListener('page:swapped', bind); // the instant language switch puts a new page in
}
