// Welcome tour (3 slides) in a native <dialog>: Esc and "Skip" close it, focus stays inside.
export function initTour() {
    const dialog = document.querySelector('dialog[data-tour]');
    if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== 'function') return;

    const slides = [...dialog.querySelectorAll('[data-tour-slide]')];
    const back = dialog.querySelector('[data-tour-back]');
    const next = dialog.querySelector('[data-tour-next]');
    const done = dialog.querySelector('[data-tour-done]');
    let current = 0;

    const show = (index) => {
        current = Math.max(0, Math.min(index, slides.length - 1));
        slides.forEach((slide, i) => { slide.hidden = i !== current; });
        back.hidden = current === 0;
        next.hidden = current === slides.length - 1;
        done.hidden = current !== slides.length - 1;
        (done.hidden ? next : done).focus();
    };

    back.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => show(current + 1));

    dialog.showModal();
    show(0);
}
