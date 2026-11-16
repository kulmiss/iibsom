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
        restore: (ids) => {
            photos = ids.map((id) => ({ key: String(id), id, preview: null, state: 'done' }));
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

const form = document.querySelector('[data-post-form]');
if (form) {
    const tpl = document.querySelector('template[data-i18n]');
    const t = { ...tpl?.dataset };
    const photos = initPhotos(form, t);
    initAreas(form);
    initDraft(form, photos);
}
