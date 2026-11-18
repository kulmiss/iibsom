/**
 * Post-an-ad form helpers (phones first, slow internet in mind):
 * - photos are shrunk ON THE PHONE before upload (saves data), uploaded one by one
 * - reorder with arrow buttons (touch) or drag (desktop); first photo = cover
 * - everything typed is saved on the phone (draft) and comes back if the
 *   connection drops or the page is closed
 */

const MAX_SIDE = 1600;
const QUALITY = 0.85;

function csrf() {
    return document.querySelector('input[name="_token"]')?.value ?? '';
}

/** Resize on the device; returns a Blob (JPEG). Falls back to the original file. */
async function shrink(file) {
    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
        if (scale === 1 && file.size < 1_500_000) return file;

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
        return blob ?? file;
    } catch {
        return file;
    }
}

function initPhotos(form, t) {
    const list = form.querySelector('[data-photo-list]');
    const input = form.querySelector('[data-photo-input]');
    const limitMsg = form.querySelector('[data-photo-limit]');
    const max = Number(form.dataset.maxPhotos || 8);
    const uploadUrl = form.dataset.uploadUrl;
    const deleteUrl = form.dataset.deleteUrl;

    /** @type {{key:string, id:number|null, preview:string|null, state:'uploading'|'done'|'failed'}[]} */
    let photos = [];

    const render = () => {
        list.innerHTML = '';
        photos.forEach((p, i) => {
            const li = document.createElement('li');
            li.className = 'relative aspect-square overflow-hidden rounded-xl bg-stone-100 ring-1 ring-stone-200';
            li.draggable = p.state === 'done';
            li.dataset.index = String(i);

            if (p.preview) {
                const img = document.createElement('img');
                img.src = p.preview;
                img.alt = '';
                img.className = 'size-full object-cover';
                li.append(img);
            }

            if (i === 0 && p.state === 'done') {
                const badge = document.createElement('span');
                badge.className = 'absolute left-1 top-1 rounded bg-brand-700 px-1.5 py-0.5 text-[10px] font-bold text-white';
                badge.textContent = t.cover;
                li.append(badge);
            }

            if (p.state !== 'done') {
                const overlay = document.createElement('span');
                overlay.className = 'absolute inset-0 flex items-center justify-center bg-black/40 p-1 text-center text-xs font-medium text-white';
                overlay.textContent = p.state === 'uploading' ? t.uploading : t.failed;
                li.append(overlay);
            }

            const bar = document.createElement('div');
            bar.className = 'absolute inset-x-0 bottom-0 flex justify-between bg-black/50 text-white';
            const btn = (label, text, action, disabled) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'px-2 py-1 text-sm disabled:opacity-30';
                b.setAttribute('aria-label', label);
                b.textContent = text;
                b.disabled = disabled;
                b.addEventListener('click', action);
                return b;
            };
            bar.append(
                btn(t.left, '‹', () => move(i, i - 1), i === 0 || p.state !== 'done'),
                btn(t.remove, '✕', () => remove(i), false),
                btn(t.right, '›', () => move(i, i + 1), i === photos.length - 1 || p.state !== 'done'),
            );
            li.append(bar);

            if (p.id) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'photos[]';
                hidden.value = String(p.id);
                li.append(hidden);
            }

            list.append(li);
        });

        limitMsg?.classList.toggle('hidden', photos.length < max);
        form.dispatchEvent(new Event('photos-changed'));
    };

    const move = (from, to) => {
        if (to < 0 || to >= photos.length) return;
        const [item] = photos.splice(from, 1);
        photos.splice(to, 0, item);
        render();
    };

    const remove = async (i) => {
        const [p] = photos.splice(i, 1);
        render();
        if (p?.id) {
            fetch(`${deleteUrl}/${p.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } }).catch(() => {});
        }
    };

    const upload = async (file) => {
        const entry = { key: crypto.randomUUID(), id: null, preview: URL.createObjectURL(file), state: 'uploading' };
        photos.push(entry);
        render();

        const body = new FormData();
        body.append('photo', await shrink(file), 'photo.jpg');

        try {
            const res = await fetch(uploadUrl, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
            if (!res.ok) throw new Error(String(res.status));
            entry.id = (await res.json()).id;
            entry.state = 'done';
        } catch {
            entry.state = 'failed';
        }
        render();
    };

    input.addEventListener('change', async () => {
        const files = [...input.files].slice(0, Math.max(0, max - photos.length));
        input.value = '';
        limitMsg?.classList.toggle('hidden', photos.length + files.length <= max);
        for (const file of files) await upload(file); // one at a time: kinder to slow connections
    });

    // Desktop drag-and-drop reordering.
    let dragFrom = null;
    list.addEventListener('dragstart', (e) => { dragFrom = Number(e.target.closest('li')?.dataset.index); });
    list.addEventListener('dragover', (e) => e.preventDefault());
    list.addEventListener('drop', (e) => {
        e.preventDefault();
        const to = Number(e.target.closest('li')?.dataset.index);
        if (Number.isInteger(dragFrom) && Number.isInteger(to)) move(dragFrom, to);
        dragFrom = null;
    });

    return {
        ids: () => photos.filter((p) => p.id).map((p) => p.id),
        cover: () => photos.find((p) => p.preview && p.state !== 'failed')?.preview || null,
        restore: (ids) => {
            photos = ids.map((id) => ({ key: String(id), id, preview: null, state: 'done' }));
            render();
        },
        /** Edit: show the photos the ad already has. */
        load: (items) => {
            photos = items.map((p) => ({ key: String(p.id), id: p.id, preview: p.preview, state: 'done' }));
            render();
        },
    };
}

function initAreas(form) {
    const city = form.querySelector('[data-city-select]');
    const area = form.querySelector('[data-area-select]');
    if (!city || !area) return;

    const areas = JSON.parse(area.dataset.areas || '{}');
    const first = area.options[0].cloneNode(true);

    const fill = (selected) => {
        area.replaceChildren(first.cloneNode(true));
        for (const a of areas[city.value] ?? []) {
            const opt = new Option(a.name, a.id, false, String(a.id) === String(selected));
            area.add(opt);
        }
    };

    city.addEventListener('change', () => fill(''));
    fill(area.dataset.selected);
}

function initDraft(form, photos) {
    const key = form.dataset.draftKey;
    const status = form.querySelector('[data-draft-status]');
    const restoredMsg = document.querySelector('[data-draft-restored]');
    const hasServerErrors = document.querySelector('.form-error:not(.hidden)') !== null;

    const save = () => {
        const data = {};
        for (const el of form.elements) {
            if (!el.name || el.name === '_token' || el.name === 'photos[]' || el.type === 'file') continue;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
            if (el.type === 'hidden' && el.name === 'price_negotiable') continue;
            data[el.name] = el.value;
        }
        data.__photos = photos.ids();
        try {
            localStorage.setItem(key, JSON.stringify({ at: Date.now(), data }));
            status?.classList.remove('hidden');
        } catch { /* storage full or blocked: ignore */ }
    };

    const restore = () => {
        let saved;
        try { saved = JSON.parse(localStorage.getItem(key) || 'null'); } catch { saved = null; }
        if (!saved || hasServerErrors) return; // server already refilled the form

        for (const [name, value] of Object.entries(saved.data)) {
            if (name === '__photos') continue;
            const fields = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);
            fields.forEach((el) => {
                if (el.type === 'checkbox' || el.type === 'radio') el.checked = el.value === value;
                else if (el.type !== 'hidden') el.value = value;
            });
        }
        form.querySelector('[data-city-select]')?.dispatchEvent(new Event('change'));
        const area = form.querySelector('[data-area-select]');
        if (area && saved.data.area_id) area.value = saved.data.area_id;
        if (saved.data.__photos?.length) photos.restore(saved.data.__photos);
        restoredMsg?.classList.remove('hidden');
    };

    restore();

    let timer;
    const later = () => { clearTimeout(timer); timer = setTimeout(save, 400); };
    form.addEventListener('input', later);
    form.addEventListener('change', later);
    form.addEventListener('photos-changed', later);

    document.querySelector('[data-draft-clear]')?.addEventListener('click', () => {
        localStorage.removeItem(key);
        location.reload();
    });
}

// Price guide under the price: asks the server for similar ads' prices when brand, model or condition change.
function initPriceHint(form) {
    const box = form.querySelector('[data-price-hint]');
    if (!box) return;
    let timer;
    let last = null; // null, not '': the first question has no parameters at all
    const update = async () => {
        const params = new URLSearchParams();
        const pick = (name) => form.querySelector(`[name="${name}"]:checked, select[name="${name}"], input[type=text][name="${name}"]`)?.value || '';
        const condition = pick('condition');
        if (condition) params.set('condition', condition);
        for (const key of ['brand', 'model']) {
            const v = pick(`fields[${key}]`).trim();
            if (v) params.set(`fields[${key}]`, v);
        }
        if (box.dataset.except) params.set('except', box.dataset.except);
        const query = params.toString();
        if (query === last) return;
        last = query;
        try {
            const res = await fetch(`${box.dataset.url}?${query}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(String(res.status));
            const { hint } = await res.json();
            if (query !== last) return; // a newer question was asked meanwhile
            box.classList.toggle('hidden', !hint);
            if (hint) {
                box.querySelector('[data-price-hint-range]').textContent = hint.range;
                box.querySelector('[data-price-hint-detail]').textContent = hint.detail;
            }
        } catch {
            box.classList.add('hidden'); // the guide is only a help: never block posting
            last = null;
        }
    };
    const later = (e) => {
        const name = e.target?.name || '';
        if (name === 'condition' || name === 'fields[brand]' || name === 'fields[model]') {
            clearTimeout(timer);
            timer = setTimeout(update, 400);
        }
    };
    form.addEventListener('change', later);
    form.addEventListener('input', later);
    update();
}

const form = document.querySelector('[data-post-form]');
if (form) {
    const tpl = document.querySelector('template[data-i18n]');
    const t = { ...tpl?.dataset };
    const photos = initPhotos(form, t);
    initAreas(form);
    const existing = JSON.parse(form.dataset.photos || '[]');
    if (existing.length) photos.load(existing);
    if (form.dataset.draftKey) initDraft(form, photos); // new ads only; edits start from the saved ad
    initPriceHint(form); // after the draft is back, so the guide fits the restored brand
    initPreview(form, photos); // (a function declaration below, so it is ready here)
}

// Live card preview (switch "post.preview"): title, price, first photo and place, as buyers will see them.
function initPreview(form, photos) {
    const box = document.querySelector('[data-preview]');
    if (!box) return;
    box.hidden = false;
    const name = box.querySelector('[data-preview-name]');
    const price = box.querySelector('[data-preview-price]');
    const place = box.querySelector('[data-preview-place]');
    const img = box.querySelector('[data-preview-photo]');
    const none = box.querySelector('[data-preview-nophoto]');
    const label = (select) => (select && select.value ? select.options[select.selectedIndex]?.text.trim() : '');
    const update = () => {
        const title = form.querySelector('[name=title]')?.value.trim();
        name.textContent = title || name.dataset.empty;
        const amount = Number(form.querySelector('[name=price]')?.value || 0);
        const nego = form.querySelector('[name=price_negotiable][type=checkbox]')?.checked;
        const money = amount > 0 ? `$${amount.toLocaleString('en-US')}` : '';
        price.textContent = money && nego ? `${money} · ${price.dataset.negotiable}` : money || price.dataset.negotiable;
        place.textContent = [label(form.querySelector('[data-city-select]')), label(form.querySelector('[data-area-select]'))].filter(Boolean).join(', ');
        const cover = photos.cover();
        img.classList.toggle('hidden', !cover);
        none.classList.toggle('hidden', !!cover);
        if (cover && img.src !== cover) img.src = cover;
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('photos-changed', update);
    update();
}

// Category search on step 1 (switch "post.category_search"): English and Somali names.
const catSearch = document.querySelector('[data-cat-search]');
if (catSearch) {
    document.querySelector('[data-cat-search-box]').hidden = false;
    const groups = [...document.querySelectorAll('[data-cat-group]')];
    const none = document.querySelector('[data-cat-none]');
    const opened = new Map(groups.map((g) => [g, g.open]));
    catSearch.addEventListener('input', () => {
        const words = catSearch.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        let any = false;
        groups.forEach((g) => {
            const groupHit = words.length > 0 && words.every((w) => g.dataset.names.includes(w));
            let shown = 0;
            g.querySelectorAll('[data-cat-item]').forEach((li) => {
                const hit = !words.length || groupHit || words.every((w) => li.dataset.names.includes(w));
                li.hidden = !hit;
                if (hit) shown += 1;
            });
            g.hidden = shown === 0;
            g.open = words.length ? shown > 0 : opened.get(g);
            any ||= shown > 0;
        });
        none.classList.toggle('hidden', any);
    });
}
