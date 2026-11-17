/*
 * iibsom service worker (installable website).
 * Rules: NEVER store web pages (they can be private: account, admin, drafts).
 * Only: the offline page, the app icons and ad photos. /build files are left to the
 * browser's own cache (unique names + 1-year cache header), which also keeps
 * the browser's preloading working.
 * Change VERSION to replace all caches.
 */
const VERSION = 'v2';
const STATIC = `iibsom-static-${VERSION}`;
const PHOTOS = `iibsom-photos-${VERSION}`;
const OFFLINE = '/offline.html';
const MAX_PHOTOS = 80;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll([OFFLINE, '/icons/icon-192.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('iibsom-') && ![STATIC, PHOTOS].includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

const isPhoto = (url) => /\.(webp|avif|jpe?g|png)$/i.test(url.pathname) && /\/ads\//.test(url.pathname);

async function trimPhotos() {
    const cache = await caches.open(PHOTOS);
    const keys = await cache.keys();
    await Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_PHOTOS)).map((k) => cache.delete(k)));
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    // Pages: always from the network. Offline → the offline page (never a stored copy of a page).
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }

    // App icons: cache first.
    if (url.origin === self.location.origin && url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) caches.open(STATIC).then((c) => c.put(req, res.clone()));
                return res;
            })),
        );
        return;
    }

    // Ad photos (also from the photos host / R2): show the stored copy at once, refresh it in the background.
    if (isPhoto(url)) {
        event.respondWith(
            caches.open(PHOTOS).then((cache) => cache.match(req).then((hit) => {
                const fresh = fetch(req).then((res) => {
                    if (res.ok || res.type === 'opaque') cache.put(req, res.clone()).then(trimPhotos);
                    return res;
                }).catch(() => hit);
                return hit || fresh;
            })),
        );
    }
    // Everything else (API, JSON, admin, other sites): straight to the network, nothing stored.
});
