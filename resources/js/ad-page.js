/**
 * Ad page: show the seller's number only after a tap (anti-bot),
 * copy/share link, tap a photo to zoom, and (switch "ad_stats") count
 * views and WhatsApp/call taps: only a number per ad, nothing about the visitor.
 */

const stats = document.querySelector('[data-stats]');
const count = (event) => {
    if (!stats || !navigator.sendBeacon) return;
    const body = new FormData();
    body.append('e', event);
    navigator.sendBeacon(stats.dataset.stats, body);
};
if (stats) {
    // One view per ad per browser tab (reloads don't count again).
    const key = `iibsom:seen:${stats.dataset.statsId}`;
    let seen = false;
    try { seen = sessionStorage.getItem(key) === '1'; sessionStorage.setItem(key, '1'); } catch { /* private mode */ }
    if (!seen) count('views');
}

const contact = document.querySelector('[data-contact]');
let revealed = null; // one request per page, shared by the big button and the phone contact bar

function reveal() {
    if (!contact) return Promise.reject(new Error('no contact'));
    revealed ??= (async () => {
        const btn = contact.querySelector('[data-reveal]');
        if (btn) btn.disabled = true;
        const res = await fetch(contact.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(String(res.status));
        const data = await res.json();

        const number = contact.querySelector('[data-number]');
        number.textContent = data.display;
        number.classList.remove('hidden');
        btn?.classList.add('hidden');
        contact.querySelector('[data-safety-tip]')?.classList.remove('hidden'); // switch "contact.safety_tip"

        const buttons = contact.querySelector('[data-buttons]');
        buttons.classList.remove('hidden');
        buttons.classList.add('grid');
        const wa = contact.querySelector('[data-whatsapp]');
        const call = contact.querySelector('[data-call]');
        if (wa && data.whatsapp) { wa.href = data.whatsapp; wa.classList.replace('hidden', 'flex'); wa.addEventListener('click', () => count('whatsapp'), { once: true }); }
        if (call && data.call) { call.href = data.call; call.classList.replace('hidden', 'flex'); call.addEventListener('click', () => count('call'), { once: true }); }
        return data;
    })().catch((err) => {
        revealed = null;
        const btn = contact.querySelector('[data-reveal]');
        if (btn) btn.disabled = false;
        throw err;
    });
    return revealed;
}

contact?.querySelector('[data-reveal]')?.addEventListener('click', () => { reveal().catch(() => {}); });

// Phone contact bar (switch "ads.sticky_contact"): Call / WhatsApp show the number first, then open.
document.querySelector('[data-contact-bar]')?.addEventListener('click', async (e) => {
    const link = e.target.closest('[data-bar]');
    if (!link) return;
    const kind = link.dataset.bar;
    if (kind === 'offer') {
        document.querySelector('[data-offer]')?.setAttribute('open', '');
        return; // the link still jumps to the offer box
    }
    if (kind !== 'call' && kind !== 'whatsapp') return; // chat: the link jumps to the contact box
    e.preventDefault();
    try {
        const data = await reveal();
        const target = kind === 'call' ? data.call : data.whatsapp;
        if (!target) { contact.scrollIntoView({ behavior: 'smooth' }); return; }
        count(kind);
        window.location.href = target;
    } catch {
        contact?.scrollIntoView({ behavior: 'smooth' });
    }
});

const share = document.querySelector('[data-share]');
share?.querySelector('[data-copy]')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const url = share.dataset.url;
    if (navigator.share) {
        try { await navigator.share({ title: share.dataset.title, url }); return; } catch { /* closed */ }
    }
    try {
        await navigator.clipboard.writeText(url);
        btn.textContent = btn.dataset.done;
    } catch { /* no clipboard */ }
});

// Gallery: small pictures under the photo (switch "ads.gallery_plus").
const gallery = document.querySelector('[data-gallery]');
const thumbs = document.querySelector('[data-thumbs]');
const slideIndex = (track) => Math.round(Math.abs(track.scrollLeft) / Math.max(1, track.clientWidth));
const goTo = (track, i, smooth = true) => {
    const dir = getComputedStyle(track).direction === 'rtl' ? -1 : 1;
    track.scrollTo({ left: dir * i * track.clientWidth, behavior: smooth ? 'smooth' : 'instant' });
};
if (gallery && thumbs) {
    thumbs.addEventListener('click', (e) => {
        const a = e.target.closest('[data-thumb]');
        if (!a) return;
        e.preventDefault();
        thumbs.querySelectorAll('[data-thumb]').forEach((x) => x.setAttribute('aria-current', String(x === a)));
        goTo(gallery, Number(a.dataset.thumb));
    });
    let t;
    gallery.addEventListener('scroll', () => {
        clearTimeout(t);
        t = setTimeout(() => {
            const i = slideIndex(gallery);
            thumbs.querySelectorAll('[data-thumb]').forEach((a) => a.setAttribute('aria-current', String(Number(a.dataset.thumb) === i)));
            thumbs.querySelector(`[data-thumb="${i}"]`)?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }, 80);
    }, { passive: true });
}

// Full screen viewer: all photos, swipe / arrows / keys, pinch to zoom.
const viewer = document.querySelector('[data-viewer]');
if (viewer?.showModal) {
    const track = viewer.querySelector('[data-viewer-track]');
    const counter = viewer.querySelector('[data-viewer-count]');
    const total = track.children.length;
    let current = 0; // kept here: once the dialog closes the track has no size to measure
    const paint = () => { current = slideIndex(track); counter.textContent = `${current + 1} / ${total}`; };
    track.addEventListener('scroll', () => requestAnimationFrame(paint), { passive: true });
    document.querySelectorAll('[data-zoom]').forEach((el) => {
        el.addEventListener('click', () => {
            track.querySelectorAll('img[data-src]').forEach((img) => { img.src = img.dataset.src; img.removeAttribute('data-src'); });
            viewer.showModal();
            goTo(track, Number(el.dataset.index || 0), false);
            paint();
        });
    });
    viewer.querySelector('[data-viewer-prev]')?.addEventListener('click', () => goTo(track, Math.max(0, slideIndex(track) - 1)));
    viewer.querySelector('[data-viewer-next]')?.addEventListener('click', () => goTo(track, Math.min(total - 1, slideIndex(track) + 1)));
    viewer.addEventListener('keydown', (e) => {
        const rtl = getComputedStyle(track).direction === 'rtl';
        if (e.key === 'ArrowRight') goTo(track, Math.min(total - 1, slideIndex(track) + (rtl ? -1 : 1)));
        if (e.key === 'ArrowLeft') goTo(track, Math.max(0, slideIndex(track) + (rtl ? 1 : -1)));
    });
    // Back on the gallery at the photo the person stopped on.
    viewer.addEventListener('close', () => { if (gallery) goTo(gallery, current, false); });
}

// Long descriptions: first lines + "Read more" (switch "ads.read_more"). Full text stays in the page.
const text = document.querySelector('[data-read-more]');
const more = document.querySelector('[data-read-more-button]');
if (text && more) {
    text.classList.add('line-clamp-[8]');
    if (text.scrollHeight > text.clientHeight + 4) {
        more.hidden = false;
        more.addEventListener('click', () => {
            const open = text.classList.toggle('line-clamp-[8]') === false;
            more.textContent = open ? more.dataset.less : more.dataset.more;
            more.setAttribute('aria-expanded', String(open));
        });
    } else {
        text.classList.remove('line-clamp-[8]');
    }
}

// Old single-photo zoom (switch "ads.gallery_plus" OFF).
const dialog = document.querySelector('[data-zoom-dialog]');
if (!viewer?.showModal) document.querySelectorAll('[data-zoom]').forEach((el) => {
    el.addEventListener('click', () => {
        if (!dialog?.showModal) { window.open(el.dataset.zoom, '_blank'); return; }
        dialog.querySelector('[data-zoom-img]').src = el.dataset.zoom;
        dialog.showModal();
    });
});
dialog?.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

// "Already sold? Let us know" (switch "ads.mark_unavailable"): open the report box with "Already sold" chosen.
document.querySelector('[data-mark-sold]')?.addEventListener('click', (e) => {
    const box = document.querySelector('#report details');
    const sold = box?.querySelector('input[name=reason][value=sold]');
    if (!box || !sold) return; // no JavaScript-ready box: the link just jumps to #report
    e.preventDefault();
    box.open = true;
    sold.checked = true;
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    box.querySelector('button')?.focus({ preventScroll: true });
});

// Long details (switch "ads.details_more"): show the rest on demand.
const detailsMore = document.querySelector('[data-details-more]');
detailsMore?.addEventListener('click', () => {
    const open = detailsMore.getAttribute('aria-expanded') !== 'true';
    document.querySelectorAll('[data-detail-extra]').forEach((row) => row.classList.toggle('[.js_&]:hidden', !open));
    detailsMore.setAttribute('aria-expanded', String(open));
    detailsMore.textContent = open ? detailsMore.dataset.less : detailsMore.dataset.more;
});
