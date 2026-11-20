import React from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { ArrowRight, FileText, FolderTree, Laptop, Loader2, Megaphone, Moon, Settings2, Sun, ToggleRight, User } from 'lucide-react'
import { api } from '@/lib/api'
import { findSettings, settingAllowed } from '@/lib/settings-index'
import { useSearch } from '@/context/search-provider'
import { useTheme } from '@/context/theme-provider'
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command'
import { AdSheet } from '@/features/ads/components/ad-sheet'
import { useAuthStore } from '@/stores/auth-store'
import { visibleNavGroups } from './layout/data/sidebar-data'

type Hit = { id: number | string; title: string; hint: string; staff?: boolean }
type Result = { q: string; groups: Partial<Record<'ads' | 'users' | 'categories' | 'switches' | 'pages', Hit[]>> }

const GROUPS = [
  { key: 'ads', heading: 'Ads', icon: Megaphone },
  { key: 'users', heading: 'People', icon: User },
  { key: 'categories', heading: 'Categories', icon: FolderTree },
  { key: 'switches', heading: 'Feature switches', icon: ToggleRight },
  { key: 'pages', heading: 'Pages', icon: FileText },
] as const

/** Waits a moment after the last key, so we ask the server once per word, not once per letter. */
function useDebounced(value: string, ms: number) {
  const [v, setV] = React.useState(value)
  React.useEffect(() => {
    const t = window.setTimeout(() => setV(value), ms)
    return () => window.clearTimeout(t)
  }, [value, ms])
  return v
}

/**
 * Global search (Ctrl/⌘ K or the Search box, PR 102). Admin pages and settings are found at once in the browser;
 * ads, people, categories, switches and pages come from the server (5 of each, only what this staff member may see).
 */
export function CommandMenu() {
  const navigate = useNavigate()
  const { setTheme } = useTheme()
  const { open, setOpen } = useSearch()
  const can = useAuthStore((s) => s.can)
  const user = useAuthStore((s) => s.user)
  const [q, setQ] = React.useState('')
  const [adId, setAdId] = React.useState<number | null>(null)
  const term = useDebounced(q.trim(), 150)
  const needle = q.trim().toLowerCase()

  const { data, isFetching } = useQuery({
    queryKey: ['admin-search', term],
    queryFn: async ({ signal }) => (await api.get<Result>('/search', { params: { q: term }, signal })).data,
    enabled: open && term.length >= 2,
    placeholderData: keepPreviousData,
    staleTime: 30_000,
  })
  const server = term.length >= 2 && data?.q === term ? data.groups : {}

  const run = (command: () => unknown) => {
    setOpen(false)
    setQ('')
    command()
  }

  const pages = visibleNavGroups(can, user).flatMap((g) =>
    g.items.flatMap((item) => (item.url ? [{ title: item.title, url: item.url, group: g.title }] : (item.items ?? []).map((sub) => ({ title: `${item.title} › ${sub.title}`, url: sub.url!, group: g.title }))))
  )
  const pageHits = needle ? pages.filter((p) => p.title.toLowerCase().includes(needle)) : pages
  const settingHits = findSettings(q, (e) => settingAllowed(e, can, user), 6)
  const serverCount = GROUPS.reduce((n, g) => n + (server[g.key]?.length ?? 0), 0)
  const waiting = isFetching || (term !== q.trim() && q.trim().length >= 2)

  const openHit = (key: (typeof GROUPS)[number]['key'], hit: Hit) => {
    if (key === 'ads') return run(() => setAdId(Number(hit.id)))
    if (key === 'users') return run(() => (hit.staff && can('staff.manage') ? navigate({ to: '/staff' }) : navigate({ to: '/users', search: { open: Number(hit.id) } })))
    if (key === 'categories') return run(() => navigate({ to: '/categories' }))
    if (key === 'switches') return run(() => navigate({ to: '/features' }))
    return run(() => navigate({ to: '/pages' }))
  }

  return (
    <>
      <CommandDialog
        modal
        open={open}
        onOpenChange={(o) => {
          setOpen(o)
          if (!o) setQ('')
        }}
        title='Search'
        description='Find ads, people, categories, settings and pages'
        shouldFilter={false}
      >
        {/* The server already picks the results; the list is not filtered again by the menu. */}
        <CommandInput placeholder='Search ads, people (name, email, phone), settings, pages…' value={q} onValueChange={setQ} />
        <CommandList className='max-h-[min(70vh,32rem)]'>
          {waiting && (
            <div className='flex items-center gap-2 px-4 py-2 text-xs text-muted-foreground' role='status'>
              <Loader2 className='size-3.5 animate-spin' /> Searching…
            </div>
          )}
          {needle && !waiting && pageHits.length + settingHits.length + serverCount === 0 && <CommandEmpty>Nothing found for “{q.trim()}”.</CommandEmpty>}

          {GROUPS.map(({ key, heading, icon: Icon }) =>
            server[key]?.length ? (
              <CommandGroup key={key} heading={heading}>
                {server[key]!.map((hit) => (
                  <CommandItem key={`${key}-${hit.id}`} value={`${key}-${hit.id}`} onSelect={() => openHit(key, hit)}>
                    <Icon className='text-muted-foreground' />
                    <span className='min-w-0 flex-1'>
                      <span className='block truncate'>{hit.title}</span>
                      <span className='block truncate text-xs text-muted-foreground'>{hit.hint}</span>
                    </span>
                  </CommandItem>
                ))}
              </CommandGroup>
            ) : null
          )}

          {settingHits.length > 0 && (
            <CommandGroup heading='Settings'>
              {settingHits.map((s) => (
                <CommandItem key={`set-${s.label}`} value={`set-${s.label}`} onSelect={() => run(() => navigate({ to: s.to, hash: s.hash, search: s.search } as never))}>
                  <Settings2 className='text-muted-foreground' />
                  <span className='min-w-0 flex-1'>
                    <span className='block truncate'>{s.label}</span>
                    <span className='block truncate text-xs text-muted-foreground'>{s.section}</span>
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          )}

          {pageHits.length > 0 && (
            <CommandGroup heading='Go to'>
              {pageHits.slice(0, needle ? 8 : 40).map((p) => (
                <CommandItem key={`page-${p.url}`} value={`page-${p.url}`} onSelect={() => run(() => navigate({ to: p.url }))}>
                  <ArrowRight className='text-muted-foreground' />
                  <span className='flex-1'>{p.title}</span>
                  <span className='text-xs text-muted-foreground'>{p.group}</span>
                </CommandItem>
              ))}
            </CommandGroup>
          )}

          {!needle && (
            <>
              <CommandSeparator />
              <CommandGroup heading='Theme'>
                <CommandItem value='theme-light' onSelect={() => run(() => setTheme('light'))}>
                  <Sun /> <span>Light</span>
                </CommandItem>
                <CommandItem value='theme-dark' onSelect={() => run(() => setTheme('dark'))}>
                  <Moon className='scale-90' />
                  <span>Dark</span>
                </CommandItem>
                <CommandItem value='theme-system' onSelect={() => run(() => setTheme('system'))}>
                  <Laptop />
                  <span>System</span>
                </CommandItem>
              </CommandGroup>
            </>
          )}
        </CommandList>
      </CommandDialog>
      <AdSheet adId={adId} onOpenChange={(o) => !o && setAdId(null)} />
    </>
  )
}
