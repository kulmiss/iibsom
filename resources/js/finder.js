// "All categories" page (PR 84, switch "catalog.finder"): filter by English and Somali names as people type.
function bind() {
    const input = document.querySelector('[data-finder-search]');
    if (!input) return;
    const groups = [...document.querySelectorAll('[data-finder-group]')];
    const none = document.querySelector('[data-finder-none]');

    input.addEventListener('input', () => {
        const words = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        let any = false;
        groups.forEach((g) => {
            const details = g.querySelector('details');
            const groupHit = words.length > 0 && words.every((w) => g.dataset.names.includes(w));
            let shown = 0;
            g.querySelectorAll('[data-finder-item]').forEach((li) => {
                const hit = !words.length || groupHit || words.every((w) => li.dataset.names.includes(w));
                li.hidden = !hit;
                if (hit) shown += 1;
            });
            const visible = !words.length || groupHit || shown > 0;
            g.hidden = !visible;
            if (details) details.open = words.length > 0 && visible;
            any ||= visible;
        });
        none?.classList.toggle('hidden', any);
    });
}

export function initFinder() {
    bind();
    document.addEventListener('page:swapped', bind); // the instant language switch puts a new page in
}
