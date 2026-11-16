/**
 * Switch language WITHOUT a page reload.
 *
 * Each language has its own URL (/cars, /so/cars), so pages stay cacheable.
 * On click we fetch the other-language page, swap the <main>, header,
 * footer and <title> in place, update the address bar, and remember the
 * choice in a cookie. On the next visit we swap to the remembered language.
 */

const COOKIE = 'lang';
const YEAR = 60 * 60 * 24 * 365;

export function readLangCookie() {
    const match = document.cookie.match(/(?:^|;\s*)lang=([a-z]{2})/);
    return match ? match[1] : null;
}

export function rememberLang(code) {
    document.cookie = `${COOKIE}=${code}; Max-Age=${YEAR}; Path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
}

async function swapTo(url, code, push = true) {
    const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
    if (!response.ok) {
        location.href = url; // fall back to a normal page load
        return;
    }

    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');

    document.title = doc.title;
    document.documentElement.lang = doc.documentElement.lang;
    document.documentElement.dir = doc.documentElement.dir;

    for (const selector of ['header', '[data-page]', 'footer']) {
        const next = doc.querySelector(selector);
        const current = document.querySelector(selector);
        if (next && current) current.replaceWith(next);
    }

    // Keep SEO tags in step (canonical, hreflang, description).
    for (const selector of ['link[rel="canonical"]', 'meta[name="description"]']) {
        const next = doc.querySelector(selector);
        const current = document.querySelector(selector);
        if (next && current) current.replaceWith(next);
    }

    if (push) history.pushState({ lang: code }, '', url);
    rememberLang(code);
    document.dispatchEvent(new Event('page:swapped')); // page scripts fill their parts again
}

export function initLanguageSwitch() {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-lang-switch]');
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey) return;

        event.preventDefault();
        swapTo(link.href, link.dataset.langSwitch);
    });

    window.addEventListener('popstate', () => {
        swapTo(location.href, document.documentElement.lang, false);
    });

    // Returning visitor: show their remembered language.
    const wanted = readLangCookie();
    if (wanted && wanted !== document.documentElement.lang) {
        const link = document.querySelector(`link[rel="alternate"][hreflang="${wanted}"]`);
        if (link) swapTo(link.href, wanted, false).then(() => history.replaceState({ lang: wanted }, '', link.href));
    }
}
