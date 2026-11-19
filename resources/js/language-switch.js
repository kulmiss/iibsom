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
    // Pages with their own script (ad page, Post an ad, My ads) are loaded fully: swapping only their parts
    // would leave buttons like "Show number" without their script (found in PR 84).
    if (document.querySelector('meta[name="page-script"]')) {
        rememberLang(code);
        if (push) location.assign(url);
        else location.replace(url); // back/forward: the address is already this one
        return;
    }

    const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
    if (!response.ok) {
        location.href = url; // fall back to a normal page load
        return;
    }

    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');

    // The same when the page in the other language has its own script.
    if (doc.querySelector('meta[name="page-script"]')) {
        rememberLang(code);
        if (push) location.assign(url);
        else location.replace(url);
        return;
    }

    document.title = doc.title;
    document.documentElement.lang = doc.documentElement.lang;
    document.documentElement.dir = doc.documentElement.dir;

    for (const selector of ['header', '[data-page]', '[data-site-footer]', '[data-bottom-nav]']) {
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
        link.closest('details')?.removeAttribute('open');
        swapTo(link.href, link.dataset.langSwitch);
    });

    // The globe menu: close it with Escape (focus back on the globe) or a tap outside.
    document.addEventListener('keydown', (event) => {
        const menu = document.querySelector('details[data-lang-menu][open]');
        if (event.key !== 'Escape' || !menu) return;
        menu.removeAttribute('open');
        menu.querySelector('summary')?.focus();
    });
    document.addEventListener('click', (event) => {
        const menu = document.querySelector('details[data-lang-menu][open]');
        if (menu && !menu.contains(event.target)) menu.removeAttribute('open');
    });

    // Back/forward between languages. Only our own history entries carry a language: a jump to
    // "#filters" or "#report" also fires popstate (with no state), and must NOT reload the page parts
    // (that would drop the target, so the phone filter sheet would not open).
    if (!history.state || !history.state.lang) {
        history.replaceState({ ...(history.state || {}), lang: document.documentElement.lang }, '');
    }
    window.addEventListener('popstate', (event) => {
        const lang = event.state?.lang;
        if (!lang || lang === document.documentElement.lang) return;
        swapTo(location.href, lang, false);
    });

    // Returning visitor: show their remembered language.
    const wanted = readLangCookie();
    if (wanted && wanted !== document.documentElement.lang) {
        const link = document.querySelector(`link[rel="alternate"][hreflang="${wanted}"]`);
        if (link) swapTo(link.href, wanted, false).then(() => history.replaceState({ lang: wanted }, '', link.href));
    }
}
