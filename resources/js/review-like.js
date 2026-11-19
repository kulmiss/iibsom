// "Helpful" on seller ratings (PR 85, switch "ratings.likes"): like / un-like without leaving the page.
// One listener on the page, so it keeps working after the instant language switch. The form works without JavaScript.
export function initReviewLike() {
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-review-like]')) return;
        event.preventDefault();

        const button = form.querySelector('button');
        if (!button || button.disabled) return;
        button.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.status === 401 || res.status === 419) {
                window.location.reload(); // signed out or the page is too old
                return;
            }
            if (!res.ok) return; // e.g. too many taps: nothing changed
            const data = await res.json();
            button.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
            const count = form.querySelector('[data-like-count]');
            if (count) count.textContent = data.count > 0 ? String(data.count) : '';
        } catch {
            form.submit(); // network trouble: a normal page load
        } finally {
            button.disabled = false;
        }
    });
}
