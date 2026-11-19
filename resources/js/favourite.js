// Save / un-save an ad without leaving the page (the form still works without JavaScript).
export function initFavourites() {
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-favourite]')) return;
        e.preventDefault();

        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                if (data.message) window.alert(data.message);
                return;
            }
            const on = data.saved === true;
            // Every Save button for this ad (the ad page has one in the phone top bar and one by the title).
            document.querySelectorAll('form[data-favourite]').forEach((f) => {
                if (f.action !== form.action) return;
                const b = f.querySelector('button');
                if (!b) return;
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                b.setAttribute('aria-label', on ? b.dataset.labelOn : b.dataset.labelOff);
                const text = f.querySelector('[data-favourite-text]');
                if (text) text.textContent = on ? b.dataset.textOn : b.dataset.textOff;
            });
            document.dispatchEvent(new CustomEvent('favourite:changed', { detail: { saved: on } }));
        } catch {
            form.submit(); // network trouble: fall back to a normal page load
        } finally {
            button.disabled = false;
        }
    });
}
