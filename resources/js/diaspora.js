// Buyers abroad (switch "diaspora"): reads the phone's time zone in the browser only.
// Nothing is sent or stored on the server; the chosen money is kept on this phone.
const ZONES = [
    [/^Europe\/London$/, 'GBP'],
    [/^Europe\/Stockholm$/, 'SEK'],
    [/^Europe\/Oslo$/, 'NOK'],
    [/^Europe\//, 'EUR'],
    [/^America\/(Toronto|Vancouver|Edmonton|Winnipeg|Halifax|Regina|St_Johns|Montreal)$/, 'CAD'],
    [/^Australia\//, 'AUD'],
    [/^Asia\/Dubai$/, 'AED'],
    [/^Africa\/Nairobi$/, 'KES'],
];
const KEY = 'iibsom-currency';

export function initDiaspora() {
    const box = document.querySelector('[data-diaspora]');
    if (!box) return;
    let zone = '';
    try { zone = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch { /* old browser */ }
    if (!zone || zone === 'Africa/Mogadishu') return; // in Somalia: nothing to show
    box.hidden = false;

    const usd = Number(box.dataset.usd);
    const rates = JSON.parse(box.dataset.rates || '{}');
    const line = box.querySelector('[data-diaspora-price]');
    const select = box.querySelector('[data-diaspora-currency]');
    if (!line || !select || !usd) return;

    let saved = null;
    try { saved = localStorage.getItem(KEY); } catch { /* storage blocked */ }
    const guess = ZONES.find(([re]) => re.test(zone))?.[1];
    const code = [saved, guess].find((c) => c && rates[c]);
    if (!code) return; // e.g. USA: the price is already in dollars

    const show = (c) => {
        const value = usd * Number(rates[c]);
        let text;
        try {
            text = new Intl.NumberFormat(box.dataset.locale, { style: 'currency', currency: c, maximumSignificantDigits: 3 }).format(value);
        } catch {
            text = `${Math.round(value)} ${c}`;
        }
        line.querySelector('[data-diaspora-amount]').textContent = `≈ ${text}`;
    };
    select.value = code;
    show(code);
    line.hidden = false;
    select.addEventListener('change', () => {
        show(select.value);
        try { localStorage.setItem(KEY, select.value); } catch { /* storage blocked */ }
    });
}
