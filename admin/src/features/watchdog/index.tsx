import { useQuery } from '@tanstack/react-query'
import { getRouteApi } from '@tanstack/react-router'
import { RefreshCw } from 'lucide-react'
import { api } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Page } from '@/components/layout/page'
import { History } from './history'
import { Overview } from './overview'
import { Settings } from './settings'
import { type Overview as OverviewData, type SettingsData } from './types'

const route = getRouteApi('/_authenticated/watchdog')

/** Admin → Watchdog (PR 96): one status, cards per area, open problems, please-check list, history, settings. */
export function WatchdogPage() {
  const { tab } = route.useSearch()
  const navigate = route.useNavigate()
  const overview = useQuery({
    queryKey: ['watchdog', 'overview'],
    queryFn: async () => (await api.get<OverviewData>('/watchdog')).data,
    refetchInterval: 60_000,
  })
  const settings = useQuery({
    queryKey: ['watchdog', 'settings'],
    queryFn: async () => (await api.get<SettingsData>('/watchdog/settings')).data,
  })

  return (
    <Page
      title='Watchdog'
      help='watchdog'
      description='Watches the site day and night (health, security, ad safety, backups) and tells you by email or Telegram.'
      actions={
        <Button variant='outline' onClick={() => overview.refetch()} disabled={overview.isFetching}>
          <RefreshCw className={cn(overview.isFetching && 'animate-spin')} /> Refresh
        </Button>
      }
    >
      <Tabs value={tab} onValueChange={(v) => navigate({ search: { tab: v as typeof tab } })}>
        <TabsList className='mb-2'>
          <TabsTrigger value='overview'>Overview</TabsTrigger>
          <TabsTrigger value='history'>History</TabsTrigger>
          <TabsTrigger value='settings'>Settings</TabsTrigger>
        </TabsList>
        <TabsContent value='overview'>{overview.data ? <Overview data={overview.data} /> : <Skeleton className='h-96' />}</TabsContent>
        <TabsContent value='history'>{settings.data ? <History areas={settings.data.areas} /> : <Skeleton className='h-96' />}</TabsContent>
        <TabsContent value='settings'>{settings.data ? <Settings key={settings.dataUpdatedAt} data={settings.data} /> : <Skeleton className='h-96' />}</TabsContent>
      </Tabs>
    </Page>
  )
}
