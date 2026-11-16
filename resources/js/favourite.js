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
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            button.setAttribute('aria-label', on ? button.dataset.labelOn : button.dataset.labelOff);
            form.querySelector('[data-favourite-text]').textContent = on ? button.dataset.textOn : button.dataset.textOff;
        } catch {
            form.submit(); // network trouble: fall back to a normal page load
        } finally {
            button.disabled = false;
        }
    });
}
