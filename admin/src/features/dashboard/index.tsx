import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { Flag, ListChecks, Megaphone, Users } from 'lucide-react'
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { api } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Page } from '@/components/layout/page'

type Dashboard = {
  includes_demo: boolean
  cards: Record<'ads_today' | 'ads_week' | 'ads_month' | 'ads_live' | 'ads_pending' | 'users_today' | 'users_week' | 'users_total' | 'reports_waiting', number>
  chart: { date: string; ads: number; users: number }[]
  top_categories: { name: string; total: number }[]
  top_cities: { name: string; total: number }[]
}

const nf = new Intl.NumberFormat('en')

export function Dashboard() {
  const [range, setRange] = useState<'7' | '30' | '90'>('30')
  const { data, isLoading } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get<Dashboard>('/dashboard')).data,
    refetchInterval: 60_000,
  })

  const chart = useMemo(() => (data?.chart ?? []).slice(-Number(range)).map((d) => ({ ...d, label: d.date.slice(5) })), [data, range])

  return (
    <Page title='Dashboard' description='What is happening on iibsom.' actions={data?.includes_demo && <Badge variant='secondary'>Includes demo data</Badge>}>
      <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>
        <StatCard title='New ads' icon={Megaphone} loading={isLoading} value={data?.cards.ads_today} hint={data && `${nf.format(data.cards.ads_week)} this week · ${nf.format(data.cards.ads_month)} in 30 days`} />
        <StatCard title='Waiting for approval' icon={ListChecks} loading={isLoading} value={data?.cards.ads_pending} hint={data && `${nf.format(data.cards.ads_live)} ads live now`} to='/ads/pending' highlight={!!data?.cards.ads_pending} />
        <StatCard title='New users today' icon={Users} loading={isLoading} value={data?.cards.users_today} hint={data && `${nf.format(data.cards.users_week)} this week · ${nf.format(data.cards.users_total)} total`} to='/users' />
        <StatCard title='Reports waiting' icon={Flag} loading={isLoading} value={data?.cards.reports_waiting} hint='Ads people reported' to='/reports' highlight={!!data?.cards.reports_waiting} />
      </div>

      <Card>
        <CardHeader className='flex flex-row flex-wrap items-center justify-between gap-2'>
          <div>
            <CardTitle>New ads and users</CardTitle>
            <CardDescription>Per day</CardDescription>
          </div>
          <Tabs value={range} onValueChange={(v) => setRange(v as typeof range)}>
            <TabsList>
              <TabsTrigger value='7'>7 days</TabsTrigger>
              <TabsTrigger value='30'>30 days</TabsTrigger>
              <TabsTrigger value='90'>3 months</TabsTrigger>
            </TabsList>
          </Tabs>
        </CardHeader>
        <CardContent className='h-72 ps-0'>
          {isLoading ? (
            <Skeleton className='ms-6 h-full' />
          ) : (
            <ResponsiveContainer width='100%' height='100%'>
              <AreaChart data={chart} margin={{ left: 0, right: 12 }}>
                <CartesianGrid strokeDasharray='3 3' className='stroke-muted' vertical={false} />
                <XAxis dataKey='label' tickLine={false} axisLine={false} fontSize={12} minTickGap={24} />
                <YAxis allowDecimals={false} tickLine={false} axisLine={false} fontSize={12} width={36} />
                <Tooltip contentStyle={{ background: 'var(--popover)', border: '1px solid var(--border)', borderRadius: 8, color: 'var(--popover-foreground)' }} />
                <Area type='monotone' dataKey='ads' name='Ads' stroke='var(--primary)' fill='var(--primary)' fillOpacity={0.15} strokeWidth={2} />
                <Area type='monotone' dataKey='users' name='Users' stroke='var(--chart-2)' fill='var(--chart-2)' fillOpacity={0.12} strokeWidth={2} />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>

      <div className='grid gap-4 lg:grid-cols-2'>
        <TopList title='Top categories' description='Live ads' rows={data?.top_categories} loading={isLoading} />
        <TopList title='Top cities' description='Live ads' rows={data?.top_cities} loading={isLoading} />
      </div>
    </Page>
  )
}

function StatCard(props: { title: string; icon: React.ElementType; value?: number; hint?: string | false; loading: boolean; to?: string; highlight?: boolean }) {
  const Icon = props.icon
  const body = (
    <Card className={props.highlight ? 'border-primary/60' : undefined}>
      <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
        <CardTitle className='text-sm font-medium'>{props.title}</CardTitle>
        <Icon className='size-4 text-muted-foreground' />
      </CardHeader>
      <CardContent>
        {props.loading ? <Skeleton className='h-8 w-20' /> : <div className='text-3xl font-bold'>{nf.format(props.value ?? 0)}</div>}
        {props.hint && <p className='mt-1 text-xs text-muted-foreground'>{props.hint}</p>}
      </CardContent>
    </Card>
  )
  return props.to ? (
    <Link to={props.to} className='rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'>
      {body}
    </Link>
  ) : (
    body
  )
}

function TopList({ title, description, rows, loading }: { title: string; description: string; rows?: { name: string; total: number }[]; loading: boolean }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className='h-64'>
        {loading ? (
          <Skeleton className='h-full' />
        ) : rows && rows.length > 0 ? (
          <ResponsiveContainer width='100%' height='100%'>
            <BarChart data={rows} layout='vertical' margin={{ left: 8, right: 16 }}>
              <XAxis type='number' allowDecimals={false} hide />
              <YAxis type='category' dataKey='name' width={130} tickLine={false} axisLine={false} fontSize={12} />
              <Tooltip cursor={{ fill: 'var(--muted)' }} contentStyle={{ background: 'var(--popover)', border: '1px solid var(--border)', borderRadius: 8 }} />
              <Bar dataKey='total' name='Ads' fill='var(--primary)' radius={[0, 6, 6, 0]} />
            </BarChart>
          </ResponsiveContainer>
        ) : (
          <p className='flex h-full items-center justify-center text-sm text-muted-foreground'>No live ads yet.</p>
        )}
      </CardContent>
    </Card>
  )
}
