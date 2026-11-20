// Not loaded by any page (PR 98). It only makes the build copy every category icon to /build with a
// unique name, so the home side panels can point to it (Vite::asset) and browsers keep it for a year.
import.meta.glob('../svg/icons/*.svg', { eager: true, query: '?url&no-inline', import: 'default' });
