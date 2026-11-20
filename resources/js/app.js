import { initLanguageSwitch } from './language-switch';
import { initCardSave } from './card-save';
import { initCategoryMenu } from './category-menu';
import { initChat } from './chat';
import { initCopyText } from './copy-text';
import { initDiaspora } from './diaspora';
import { initFavourites } from './favourite';
import { initSavedCount } from './saved-count';
import { initFilters } from './filters';
import { initFinder } from './finder';
import { initFooter } from './footer';
import { initForYou } from './for-you';
import { initGuestReport } from './guest-report';
import { initInstallCard, initPwa } from './pwa';
import { initPlacePicker } from './place-picker';
import { initReviewLike } from './review-like';
import { initSearchSuggest } from './search-suggest';
import { initShowMore } from './show-more';
import { initTheme } from './theme';
import { initTour } from './tour';

initLanguageSwitch();
initCardSave();
initCategoryMenu();
initChat();
initCopyText();
initDiaspora();
initFavourites();
initSavedCount();
initFilters();
initFinder();
initFooter();
initForYou();
initGuestReport();
initPwa();
initInstallCard();
initPlacePicker();
initReviewLike();
initSearchSuggest();
initShowMore();
initTheme();
initTour();

// After a successful post, drop the drafts saved on this phone.
if (document.querySelector('[data-post-done]')) {
    try {
        Object.keys(localStorage).filter((k) => k.startsWith('iibsom-draft-')).forEach((k) => localStorage.removeItem(k));
    } catch { /* storage blocked */ }
}
