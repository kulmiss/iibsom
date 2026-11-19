// The number of saved ads on the Saved icons (PR 84, switch "account.saved_count"). Saving or un-saving
// on the page changes it at once; the next page load gets the real number from the server.
export function initSavedCount() {
    document.addEventListener('favourite:changed', (event) => {
        const step = event.detail?.saved === true ? 1 : -1;
        document.querySelectorAll('[data-saved-count]').forEach((badge) => {
            const n = Math.max(0, (parseInt(badge.dataset.savedCount, 10) || 0) + step);
            badge.dataset.savedCount = String(n);
            badge.textContent = n > 99 ? '99+' : String(n);
            badge.classList.toggle('hidden', n === 0);
        });
    });
}
