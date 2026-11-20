import { useEffect } from 'react'
import { Link, Outlet, useLocation } from '@tanstack/react-router'
import { ExternalLink, FileText, FlaskConical, House, Palette, Settings2, ShieldCheck, Siren, ToggleRight, UserCog } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { HelpButton } from '@/features/guide/help-button'
import { SettingFinder } from './components/setting-finder'

type Item = { title: string; desc: string; href: string; icon: typeof Settings2; permission?: string; feature?: 'watchdog' | 'home_page'; search?: Record<string, string> }

const PAGES: Item[] = [
  { title: 'Site rules', desc: 'Ads, languages, words, emails, footer', href: '/settings', icon: Settings2, permission: 'settings.manage' },
  { title: 'Look and colours', desc: 'Website colour, admin colour, theme', href: '/settings/appearance', icon: Palette },
  { title: 'Your sign-in', desc: 'Two-step sign-in (2FA)', href: '/settings/security', icon: ShieldCheck },
  { title: 'Demo data', desc: 'Show or delete practice data', href: '/settings/demo', icon: FlaskConical, permission: 'demo.manage' },
]

/** Settings that live on their own pages: one click from here. */
const ELSEWHERE: Item[] = [
  { title: 'Watchdog alerts', desc: 'Email, Telegram, digest', href: '/watchdog', search: { tab: 'settings' }, icon: Siren, permission: 'watchdog.manage', feature: 'watchdog' },
  { title: 'Feature switches', desc: 'Turn features on and off', href: '/features', icon: ToggleRight, permission: 'features.manage' },
  { title: 'Home page parts', desc: 'Show or hide parts', href: '/home-page', icon: House, permission: 'features.manage', feature: 'home_page' },
  { title: 'Staff and roles', desc: 'Who can do what', href: '/staff', icon: UserCog, permission: 'staff.manage' },
  { title: 'Pages', desc: 'About, Terms, Privacy…', href: '/pages', icon: FileText, permission: 'pages.manage' },
]

/** Settings (PR 101): find any setting on top, the setting pages and the other places with settings in one menu. */
export function Settings() {
  const can = useAuthStore((st) => st.can)
  const user = useAuthStore((st) => st.user)
  const { pathname, hash } = useLocation()
  const allowed = (i: Item) => (!i.permission || can(i.permission)) && (!i.feature || !!user?.[i.feature])
  const current = pathname.replace(/\/$/, '') || '/settings'

  // Jump to a field opened from "Find a setting" or the global search (#id), and show it for a moment.
  useEffect(() => {
    if (!hash) return
    const t = window.setTimeout(() => {
      const el = document.getElementById(hash)
      if (!el) return
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
      const box = el.closest('[data-setting]') ?? el
      box.classList.add('ring-2', 'ring-primary', 'ring-offset-2', 'rounded-md')
      window.setTimeout(() => box.classList.remove('ring-2', 'ring-primary', 'ring-offset-2'), 2000)
      if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) el.focus({ preventScroll: true })
    }, 150)
    return () => window.clearTimeout(t)
  }, [hash, pathname])

  const link = (i: Item, small = false) => {
    const Icon = i.icon
    const on = current === i.href
    return (
      <Link
        key={i.href}
        to={i.href}
        search={i.search as never}
        className={cn('flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-accent', on && 'bg-accent font-medium', small && 'py-1.5')}
        aria-current={on ? 'page' : undefined}
      >
        <Icon className='size-4 shrink-0 text-muted-foreground' />
        <span className='min-w-0'>
          <span className='block truncate'>{i.title}</span>
          {!small && <span className='block truncate text-xs font-normal text-muted-foreground'>{i.desc}</span>}
        </span>
        {small && <ExternalLink className='ms-auto size-3.5 shrink-0 text-muted-foreground' aria-hidden />}
      </Link>
    )
  }

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <ConfigDrawer />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='mb-6 flex flex-wrap items-end justify-between gap-4'>
          <div>
            <div className='flex items-center gap-1'>
              <h1 className='text-2xl font-bold tracking-tight md:text-3xl'>Settings</h1>
              <HelpButton section='settings' />
            </div>
            <p className='text-muted-foreground'>Everything you can change, in one place.</p>
          </div>
          <div className='w-full sm:w-80'>
            <SettingFinder />
          </div>
        </div>
        <div className='flex flex-col gap-6 lg:flex-row lg:gap-10'>
          <aside className='lg:sticky lg:top-20 lg:w-64 lg:shrink-0 lg:self-start'>
            <nav aria-label='Settings pages' className='grid grid-cols-2 gap-1 sm:grid-cols-4 lg:grid-cols-1'>
              {PAGES.filter(allowed).map((i) => link(i))}
            </nav>
            {ELSEWHERE.some(allowed) && (
              <div className='mt-4 hidden lg:block'>
                <p className='px-3 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase'>Other places</p>
                <nav aria-label='Other places with settings' className='grid gap-0.5'>
                  {ELSEWHERE.filter(allowed).map((i) => link(i, true))}
                </nav>
              </div>
            )}
          </aside>
          <div className='flex min-w-0 flex-1'>
            <Outlet />
          </div>
        </div>
        {ELSEWHERE.some(allowed) && (
          <div className='mt-2 lg:hidden'>
            <p className='pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase'>Other places with settings</p>
            <nav aria-label='Other places with settings' className='grid gap-0.5 sm:grid-cols-2'>
              {ELSEWHERE.filter(allowed).map((i) => link(i, true))}
            </nav>
          </div>
        )}
      </Main>
    </>
  )
}
