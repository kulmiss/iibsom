// "Report this ad" without signing in: when the form opens, ask for a fresh time stamp.
// The ad page may come from the page cache (minutes old), and the server drops forms sent back
// faster than a person could. No cookie is sent or kept. Without JavaScript the stamp in the page is used.
async function refresh(form) {
    try {
        const res = await fetch(form.dataset.guestReport, { credentials: 'omit', headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        form.querySelector('input[name="t"]').value = data.t;
        const label = form.querySelector('label[for="report-sum"]');
        if (label && data.sum) label.textContent = data.sum;
    } catch {
        // Offline or blocked: the stamp in the page still works.
    }
}

function bind() {
    document.querySelectorAll('form[data-guest-report]:not([data-bound])').forEach((form) => {
        const details = form.closest('details');
        if (!details) return;
        form.dataset.bound = '1';
        if (details.open) refresh(form);
        details.addEventListener('toggle', () => details.open && refresh(form));
    });
}

export function initGuestReport() {
    bind();
    document.addEventListener('page:swapped', bind); // the instant language switch puts a new page in
}
