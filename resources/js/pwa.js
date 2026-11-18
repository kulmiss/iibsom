// Installable website: register the service worker when the switch is ON,
// remove it (and its caches) when the switch is OFF.
export function initPwa() {
    if (!('serviceWorker' in navigator)) return;
    const on = document.documentElement.dataset.pwa === 'on';

    if (on) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => { /* no SW: site works normally */ });
        });
        return;
    }

    navigator.serviceWorker.getRegistrations().then((regs) => regs.forEach((r) => r.unregister())).catch(() => {});
    if ('caches' in window) {
        caches.keys().then((keys) => keys.filter((k) => k.startsWith('iibsom-')).forEach((k) => caches.delete(k))).catch(() => {});
    }
}

// "Add iibsom to your phone" card on the home page (switch "pwa.install_prompt"). Only when the phone
// offers it (Android Chrome fires "beforeinstallprompt"); "Not now" hides it for 30 days on this phone.
const INSTALL_KEY = 'iibsom-install-later';
const LATER_DAYS = 30;

function laterUntil() {
    try {
        return Number(localStorage.getItem(INSTALL_KEY) || 0);
    } catch {
        return 0;
    }
}

export function initInstallCard() {
    let offer = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        const card = document.querySelector('[data-install-card]');
        if (!card || Date.now() < laterUntil()) return;
        event.preventDefault(); // we show our own small card instead of the browser's bar
        offer = event;
        card.hidden = false;
    });

    document.addEventListener('click', async (event) => {
        const card = document.querySelector('[data-install-card]');
        if (!card) return;
        if (event.target.closest('[data-install-no]')) {
            try {
                localStorage.setItem(INSTALL_KEY, String(Date.now() + LATER_DAYS * 864e5));
            } catch { /* storage blocked: hidden for this visit only */ }
            card.hidden = true;
        } else if (event.target.closest('[data-install-yes]') && offer) {
            card.hidden = true;
            offer.prompt();
            await offer.userChoice.catch(() => null);
            offer = null;
        }
    });

    window.addEventListener('appinstalled', () => {
        const card = document.querySelector('[data-install-card]');
        if (card) card.hidden = true;
    });
}
