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
