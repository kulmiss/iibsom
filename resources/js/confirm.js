// Ask before forms marked with data-confirm are sent (delete, mark sold).
document.addEventListener('submit', (e) => {
    const msg = e.target instanceof HTMLFormElement ? e.target.dataset.confirm : null;
    if (msg && !window.confirm(msg)) e.preventDefault();
});
