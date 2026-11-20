/**
 * Every setting the staff can change, with the words people may type to find it (PR 101).
 * Used by "Find a setting" on the Settings page and by the admin's global search.
 * `to` + `hash` open the page and jump to the field (the field's id).
 */
export type SettingEntry = {
  label: string
  section: string
  words: string
  to: string
  hash?: string
  search?: Record<string, string>
  permission?: string
  feature?: 'watchdog' | 'boosts' | 'diaspora' | 'home_page' | 'verified_badge'
}

export const SETTINGS_INDEX: SettingEntry[] = [
  { label: 'Ads end after (days)', section: 'Ads and posting', words: 'expiry expire end days renew', to: '/settings', hash: 'ads_expiry_days', permission: 'settings.manage' },
  { label: 'Ads per person per day', section: 'Ads and posting', words: 'limit spam per day max', to: '/settings', hash: 'ads_max_per_day', permission: 'settings.manage' },
  { label: 'Photos per ad', section: 'Ads and posting', words: 'pictures images photos max', to: '/settings', hash: 'ads_max_photos', permission: 'settings.manage' },
  { label: 'Hide an ad after this many reports', section: 'Ads and posting', words: 'reports auto hide report', to: '/settings', hash: 'reports_auto_hide_at', permission: 'settings.manage' },
  { label: 'Languages ON', section: 'Languages', words: 'somali english language translation', to: '/settings', hash: 'languages', permission: 'settings.manage' },
  { label: 'Bad words', section: 'Safety words', words: 'bad words filter insult block', to: '/settings', hash: 'bad_words', permission: 'settings.manage' },
  { label: 'Drug words', section: 'Safety words', words: 'drugs khat qaad words block', to: '/settings', hash: 'drug_words', permission: 'settings.manage' },
  { label: 'Weekly summary email', section: 'Emails', words: 'weekly email summary report monday', to: '/settings', hash: 'weekly_emails', permission: 'settings.manage' },
  { label: 'Help email for customers', section: 'Emails', words: 'support help contact email', to: '/settings', hash: 'support_email', permission: 'settings.manage' },
  { label: 'Verified badge price for businesses', section: 'Verified badge', words: 'verified badge id price business free', to: '/settings', hash: 'verify_business_price', permission: 'settings.manage', feature: 'verified_badge' },
  { label: 'Payments for boosts', section: 'Payments', words: 'payment merchant evc zaad sahal boost money test mode', to: '/settings', hash: 'payments', permission: 'settings.manage', feature: 'boosts' },
  { label: 'Prices for buyers abroad', section: 'Buyers abroad', words: 'diaspora currency exchange rate euro pound', to: '/settings', hash: 'abroad', permission: 'settings.manage', feature: 'diaspora' },
  { label: 'Footer links and social pages', section: 'Footer', words: 'footer facebook telegram whatsapp tiktok instagram youtube social links', to: '/settings', hash: 'footer', permission: 'settings.manage' },
  { label: 'Website colour', section: 'Look and colours', words: 'colour color brand website theme', to: '/settings/appearance', hash: 'site-color', permission: 'settings.manage' },
  { label: 'Admin colour', section: 'Look and colours', words: 'colour color admin brand', to: '/settings/appearance', hash: 'admin-color' },
  { label: 'Light or dark theme', section: 'Look and colours', words: 'dark light theme mode font', to: '/settings/appearance', hash: 'theme' },
  { label: 'Two-step sign-in (2FA)', section: 'Your sign-in', words: 'security 2fa two factor password code authenticator', to: '/settings/security' },
  { label: 'Demo data', section: 'Demo data', words: 'demo practice fake test data delete', to: '/settings/demo', permission: 'demo.manage' },
  { label: 'Watchdog alerts (email, Telegram)', section: 'Watchdog', words: 'watchdog alerts telegram email digest quiet hours monitor', to: '/watchdog', search: { tab: 'settings' }, permission: 'watchdog.manage', feature: 'watchdog' },
  { label: 'Feature switches', section: 'Other places', words: 'switch feature on off enable disable', to: '/features', permission: 'features.manage' },
  { label: 'Home page parts', section: 'Other places', words: 'home landing page sections hide show', to: '/home-page', permission: 'features.manage', feature: 'home_page' },
  { label: 'Staff and roles', section: 'Other places', words: 'staff admin moderator role team', to: '/staff', permission: 'staff.manage' },
  { label: 'Pages (About, Terms…)', section: 'Other places', words: 'about terms privacy safety page text', to: '/pages', permission: 'pages.manage' },
]

/** Entries this staff member may open, best matches first (words that start with the query rank higher). */
export function findSettings(query: string, allowed: (e: SettingEntry) => boolean, limit = 8): SettingEntry[] {
  const q = query.trim().toLowerCase()
  if (!q) return []
  const scored = SETTINGS_INDEX.filter(allowed)
    .map((e) => {
      const hay = `${e.label} ${e.section} ${e.words}`.toLowerCase()
      const label = e.label.toLowerCase()
      const score = label.startsWith(q) ? 3 : hay.split(/[^a-z0-9]+/).some((w) => w.startsWith(q)) ? 2 : hay.includes(q) ? 1 : 0
      return { e, score }
    })
    .filter((x) => x.score > 0)
  return scored.sort((a, b) => b.score - a.score).slice(0, limit).map((x) => x.e)
}

/** True when this staff member may open the entry (permission + the feature it needs). */
export function settingAllowed(e: SettingEntry, can: (p: string) => boolean, user: Partial<Record<NonNullable<SettingEntry['feature']>, boolean>> | null): boolean {
  return (!e.permission || can(e.permission)) && (!e.feature || !!user?.[e.feature])
}
