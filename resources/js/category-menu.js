// Home page category list (desktop): the arrow button opens/closes a category's side panel
// (for touch screens and keyboards; mouse users get it on hover). Escape closes it.
export function initCategoryMenu() {
    const close = (except) => document.querySelectorAll('[data-cat-panel][data-open]').forEach((panel) => {
        if (panel === except) return;
        panel.removeAttribute('data-open');
        document.querySelector(`[aria-controls="${panel.id}"]`)?.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cat-toggle]');
        if (!button) {
            if (!event.target.closest('[data-cat-panel]')) close(null);
            return;
        }
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) return;
        const open = !panel.hasAttribute('data-open');
        close(panel);
        panel.toggleAttribute('data-open', open);
        button.setAttribute('aria-expanded', String(open));
        if (open) panel.querySelector('a')?.focus();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const open = document.querySelector('[data-cat-panel][data-open]');
        if (!open) return;
        close(null);
        document.querySelector(`[aria-controls="${open.id}"]`)?.focus();
    });
}
