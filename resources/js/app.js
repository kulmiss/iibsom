import { initLanguageSwitch } from './language-switch';
import { initCategoryMenu } from './category-menu';
import { initChat } from './chat';
import { initCopyText } from './copy-text';
import { initDiaspora } from './diaspora';
import { initFavourites } from './favourite';
import { initFilters } from './filters';
import { initFooter } from './footer';
import { initForYou } from './for-you';
import { initPwa } from './pwa';
import { initTour } from './tour';

initLanguageSwitch();
initCategoryMenu();
initChat();
initCopyText();
initDiaspora();
initFavourites();
initFilters();
initFooter();
initForYou();
initPwa();
initTour();

// After a successful post, drop the drafts saved on this phone.
if (document.querySelector('[data-post-done]')) {
    try {
        Object.keys(localStorage).filter((k) => k.startsWith('iibsom-draft-')).forEach((k) => localStorage.removeItem(k));
    } catch { /* storage blocked */ }
}
