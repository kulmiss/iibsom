// In-website chat: send without reloading, and ask for new messages every 10 seconds
// while the page is visible (works on shared hosting: no live server needed).
// Message text is always set with textContent (never as HTML).
export function initChat() {
    const list = document.querySelector('[data-chat-list]');
    if (!list) return;
    const form = document.querySelector('[data-chat-form]');
    const error = document.querySelector('[data-chat-error]');
    let last = Number(list.dataset.last || 0);
    let busy = false;

    const add = (m) => {
        if (m.id <= last || list.querySelector(`[data-id="${m.id}"]`)) return;
        list.querySelector('[data-chat-empty]')?.remove();
        const li = document.createElement('li');
        li.id = `m${m.id}`;
        li.dataset.id = String(m.id);
        li.className = `max-w-[85%] rounded-2xl px-3 py-2 text-sm ${m.mine ? 'self-end bg-brand-700 text-white' : 'self-start bg-white ring-1 ring-stone-200'}`;
        const body = document.createElement('p');
        body.className = 'whitespace-pre-line break-words';
        body.textContent = m.body;
        const time = document.createElement('p');
        time.className = 'mt-0.5 text-end text-[11px] opacity-75';
        time.textContent = m.time;
        li.append(body, time);
        list.append(li);
        last = Math.max(last, m.id);
    };
    const toBottom = () => list.lastElementChild?.scrollIntoView({ block: 'end' });

    const poll = async () => {
        if (busy || document.visibilityState !== 'visible') return;
        busy = true;
        try {
            const res = await fetch(`${list.dataset.since}?after=${last}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            if (data.blocked && form) { window.location.reload(); return; }
            if (data.messages.length) { data.messages.forEach(add); toBottom(); }
        } catch { /* offline for a moment: try again next time */ } finally {
            busy = false;
        }
    };

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = form.querySelector('textarea');
        if (!text.value.trim()) return;
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                error.textContent = data.message || data.errors?.body?.[0] || '';
                error.hidden = !error.textContent;
                return;
            }
            error.hidden = true;
            text.value = '';
            add(data.message);
            toBottom();
        } catch {
            form.submit();
        } finally {
            button.disabled = false;
        }
    });

    toBottom();
    setInterval(poll, 10000);
    document.addEventListener('visibilitychange', poll);
}
