// Light / dark switch in the footer (switch "site.dark_mode"). The choice is kept on this device only
// (localStorage "iibsom-theme"); the small script in <head> applies it before the page draws, so nothing flashes.
const KEY = 'iibsom-theme';

function current() {
    try {
        const v = localStorage.getItem(KEY);
        return v === 'light' || v === 'dark' ? v : 'auto';
    } catch {
        return 'auto';
    }
}

const media = window.matchMedia('(prefers-color-scheme: dark)');

function apply(mode) {
    document.documentElement.dataset.theme = mode === 'auto' ? (media.matches ? 'dark' : 'light') : mode;
}

function bind() {
    document.querySelectorAll('[data-theme-switch]:not([data-bound])').forEach((group) => {
        group.dataset.bound = '1';
        group.hidden = false; // without JavaScript there is nothing to switch, so it stays hidden
        const paint = () => group.querySelectorAll('[data-theme-choice]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.themeChoice === current())));
        group.querySelectorAll('[data-theme-choice]').forEach((button) => {
            button.addEventListener('click', () => {
                const mode = button.dataset.themeChoice;
                try {
                    if (mode === 'auto') localStorage.removeItem(KEY);
                    else localStorage.setItem(KEY, mode);
                } catch { /* storage blocked: works for this page only */ }
                apply(mode);
                paint();
            });
        });
        paint();
    });
}

export function initTheme() {
    bind();
    // "Auto" follows the device when it changes (for example at sunset).
    media.addEventListener('change', () => {
        if (current() === 'auto' && document.querySelector('[data-theme-switch]')) apply('auto'); // only when the switch is on
    });
    document.addEventListener('page:swapped', bind);
}
