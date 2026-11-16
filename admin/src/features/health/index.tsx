import { useQuery } from '@tanstack/react-query'
import { CircleAlert, CircleCheck, CircleX, RefreshCw } from 'lucide-react'
import { api } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Page } from '@/components/layout/page'

type Check = { key: string; label: string; status: 'ok' | 'warn' | 'fail'; message: string; fix: string | null }
type Health = { checks: Check[]; facts: { label: string; value: string }[] }

const ICON = { ok: CircleCheck, warn: CircleAlert, fail: CircleX }
const COLOR = { ok: 'text-emerald-600', warn: 'text-amber-600', fail: 'text-destructive' }

export function HealthPage() {
  const { data, isFetching, refetch } = useQuery({ queryKey: ['health'], queryFn: async () => (await api.get<Health>('/health')).data })
  const fails = data?.checks.filter((c) => c.status === 'fail').length ?? 0

  return (
    <Page
      title='System health'
      description={data ? (fails ? `${fails} thing(s) need attention.` : 'Everything important works.') : 'Checking…'}
      actions={
        <Button variant='outline' onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw className={cn(isFetching && 'animate-spin')} /> Check again
        </Button>
      }
    >
      <div className='grid gap-4 lg:grid-cols-[1fr_22rem]'>
        <Card>
          <CardHeader>
            <CardTitle className='text-base'>Checks</CardTitle>
          </CardHeader>
          <CardContent className='divide-y'>
            {!data && <Skeleton className='h-64' />}
            {data?.checks.map((c) => {
              const Icon = ICON[c.status]
              return (
                <div key={c.key} className='flex gap-3 py-3 first:pt-0'>
                  <Icon className={cn('mt-0.5 size-5 shrink-0', COLOR[c.status])} />
                  <div>
                    <p className='font-medium'>{c.label}</p>
                    <p className='text-sm text-muted-foreground'>{c.message}</p>
                    {c.fix && <p className='mt-1 text-sm'>→ {c.fix}</p>}
                  </div>
                </div>
              )
            })}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className='text-base'>This server</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className='space-y-2 text-sm'>
              {data?.facts.map((f) => (
                <div key={f.label} className='flex justify-between gap-3'>
                  <dt className='text-muted-foreground'>{f.label}</dt>
                  <dd className={cn('text-end font-medium', f.value === 'MISSING' && 'text-destructive')}>{f.value}</dd>
                </div>
              ))}
            </dl>
          </CardContent>
        </Card>
      </div>
    </Page>
  )
}
