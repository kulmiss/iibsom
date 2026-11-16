import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'

type PageProps = {
  title: string
  description?: React.ReactNode
  actions?: React.ReactNode
  fixed?: boolean
  children: React.ReactNode
}

/** Standard admin page: top bar (search, theme, layout, profile) + title + content. */
export function Page({ title, description, actions, fixed, children }: PageProps) {
  return (
    <>
      <Header fixed>
        <Search />
        <div className='ms-auto flex items-center gap-2 sm:gap-4'>
          <ThemeSwitch />
          <ConfigDrawer />
          <ProfileDropdown />
        </div>
      </Header>
      <Main fixed={fixed} className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>{title}</h1>
            {description && <p className='text-muted-foreground'>{description}</p>}
          </div>
          {actions && <div className='flex flex-wrap gap-2'>{actions}</div>}
        </div>
        {children}
      </Main>
    </>
  )
}
