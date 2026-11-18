import { initLanguageSwitch } from './language-switch';
import { initCardSave } from './card-save';
import { initCategoryMenu } from './category-menu';
import { initChat } from './chat';
import { initCopyText } from './copy-text';
import { initDiaspora } from './diaspora';
import { initFavourites } from './favourite';
import { initFilters } from './filters';
import { initFooter } from './footer';
import { initForYou } from './for-you';
import { initGuestReport } from './guest-report';
import { initPwa } from './pwa';
import { initSearchSuggest } from './search-suggest';
import { initShowMore } from './show-more';
import { initTour } from './tour';

initLanguageSwitch();
initCardSave();
initCategoryMenu();
initChat();
initCopyText();
initDiaspora();
initFavourites();
initFilters();
initFooter();
initForYou();
initGuestReport();
initPwa();
initSearchSuggest();
initShowMore();
initTour();

// After a successful post, drop the drafts saved on this phone.
if (document.querySelector('[data-post-done]')) {
    try {
        Object.keys(localStorage).filter((k) => k.startsWith('iibsom-draft-')).forEach((k) => localStorage.removeItem(k));
    } catch { /* storage blocked */ }
}
