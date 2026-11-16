import { initLanguageSwitch } from './language-switch';
import { initForYou } from './for-you';
import { initPwa } from './pwa';

initLanguageSwitch();
initForYou();
initPwa();

// After a successful post, drop the drafts saved on this phone.
if (document.querySelector('[data-post-done]')) {
    try {
        Object.keys(localStorage).filter((k) => k.startsWith('iibsom-draft-')).forEach((k) => localStorage.removeItem(k));
    } catch { /* storage blocked */ }
}
