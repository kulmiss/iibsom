import { initLanguageSwitch } from './language-switch';
import { initChat } from './chat';
import { initCopyText } from './copy-text';
import { initFavourites } from './favourite';
import { initForYou } from './for-you';
import { initPwa } from './pwa';
import { initTour } from './tour';

initLanguageSwitch();
initChat();
initCopyText();
initFavourites();
initForYou();
initPwa();
initTour();

// After a successful post, drop the drafts saved on this phone.
if (document.querySelector('[data-post-done]')) {
    try {
        Object.keys(localStorage).filter((k) => k.startsWith('iibsom-draft-')).forEach((k) => localStorage.removeItem(k));
    } catch { /* storage blocked */ }
}
