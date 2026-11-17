// Footer columns fold on phones (tap a title to open). The page is sent with them open, so without
// JavaScript everything is simply visible; on computers they always stay open.
const PHONE = '(max-width: 1023.98px)';

function fold() {
    if (!window.matchMedia(PHONE).matches) return;
    document.querySelectorAll('details[data-footer-section]').forEach((d) => d.removeAttribute('open'));
}

export function initFooter() {
    fold();
    // Turning a tablet or widening the window to computer size: open them all again.
    window.matchMedia(PHONE).addEventListener('change', (event) => {
        if (!event.matches) document.querySelectorAll('details[data-footer-section]').forEach((d) => d.setAttribute('open', ''));
    });
    document.addEventListener('page:swapped', fold); // the language switch puts a new footer in
    // Computers: the titles are headings, not buttons.
    document.addEventListener('click', (event) => {
        const summary = event.target.closest('details[data-footer-section] > summary');
        if (summary && !window.matchMedia(PHONE).matches) event.preventDefault();
    });
}
