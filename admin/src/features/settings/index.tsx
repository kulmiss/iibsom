import { Outlet } from '@tanstack/react-router'
import { FlaskConical, Palette, Settings2, ShieldCheck } from 'lucide-react'
import { useAuthStore } from '@/stores/auth-store'
import { Separator } from '@/components/ui/separator'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { SidebarNav } from './components/sidebar-nav'
import { HelpButton } from '@/features/guide/help-button'

const allItems = [
  { title: 'General', href: '/settings', icon: <Settings2 size={18} />, permission: 'settings.manage' },
  { title: 'Appearance', href: '/settings/appearance', icon: <Palette size={18} /> },
  { title: 'Security (2FA)', href: '/settings/security', icon: <ShieldCheck size={18} /> },
  { title: 'Demo data', href: '/settings/demo', icon: <FlaskConical size={18} />, permission: 'demo.manage' },
]

export function Settings() {
  const can = useAuthStore((st) => st.can)
  const sidebarNavItems = allItems.filter((i) => !i.permission || can(i.permission))
  return (
    <>
      {/* ===== Top Heading ===== */}
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <ConfigDrawer />
        <ProfileDropdown />
      </Header>

      <Main fixed>
        <div className='space-y-0.5'>
          <div className='flex items-center gap-1'>
            <h1 className='text-2xl font-bold tracking-tight md:text-3xl'>Settings</h1>
            <HelpButton section='settings' />
          </div>
          <p className='text-muted-foreground'>
            Site settings, the admin look and your sign-in security.
          </p>
        </div>
        <Separator className='my-4 lg:my-6' />
        <div className='flex flex-1 flex-col space-y-2 overflow-hidden md:space-y-2 lg:flex-row lg:space-y-0 lg:space-x-12'>
          <aside className='top-0 lg:sticky lg:w-1/5'>
            <SidebarNav items={sidebarNavItems} />
          </aside>
          <div className='flex w-full overflow-y-hidden p-1'>
            <Outlet />
          </div>
        </div>
      </Main>
    </>
  )
}
