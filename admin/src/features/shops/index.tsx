import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format } from 'date-fns'
import { ExternalLink, Store } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Page } from '@/components/layout/page'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'

type Filter = 'waiting' | 'active' | 'ended'
type ShopRow = {
  id: number
  name: string
  slug: string
  url: string
  logo: string | null
  city: string | null
  active_until: string | null
  active: boolean
  live_ads: number
  owner: { id: number; name: string; email: string; phone: string | null; banned: boolean }
  created_at: string
}

export function Shops() {
  const qc = useQueryClient()
  const [filter, setFilter] = useState<Filter>('waiting')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['shops', filter, q, page],
    queryFn: async () => (await api.get<{ data: ShopRow[]; total: number; last_page: number }>('/shops', { params: { filter, q, page } })).data,
  })

  const activate = useMutation({
    mutationFn: ({ id, months }: { id: number; months: number }) => api.post<{ active_until: string | null }>(`/shops/${id}/activate`, { months }),
    onSuccess: (res) => {
      toast.success(res.data.active_until ? `Shop page public until ${format(new Date(res.data.active_until), 'd MMM yyyy')}.` : 'Shop page turned off.')
      qc.invalidateQueries({ queryKey: ['shops'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <Page title='Shops' help='shops' description='Business shop pages. A shop is public only while you turn it on (monthly plan, paid outside the site for now).'>
      <div className='flex flex-wrap items-center gap-2'>
        <Tabs value={filter} onValueChange={(v) => { setFilter(v as Filter); setPage(1) }}>
          <TabsList>
            <TabsTrigger value='waiting'>Waiting</TabsTrigger>
            <TabsTrigger value='active'>Active</TabsTrigger>
            <TabsTrigger value='ended'>Ended</TabsTrigger>
          </TabsList>
        </Tabs>
        <Input className='max-w-xs' placeholder='Search shop name' value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} aria-label='Search shop name' />
      </div>

      {isLoading && <Skeleton className='h-48 w-full' />}
      {data && data.data.length === 0 && <p className='py-10 text-center text-muted-foreground'>No shops here.</p>}

      <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-3'>
        {data?.data.map((s) => (
          <Card key={s.id}>
            <CardHeader className='flex flex-row items-center gap-3'>
              {s.logo ? <img src={s.logo} alt='' className='size-12 rounded-lg object-cover' /> : <Store className='size-12 rounded-lg bg-muted p-2 text-muted-foreground' />}
              <div className='min-w-0'>
                <CardTitle className='truncate text-base'>{s.name}</CardTitle>
                <p className='truncate text-xs text-muted-foreground'>/shop/{s.slug}</p>
              </div>
            </CardHeader>
            <CardContent className='space-y-1 text-sm'>
              <p>
                {s.owner.name} · {s.owner.phone ?? s.owner.email} {s.owner.banned && <Badge variant='destructive'>Banned</Badge>}
              </p>
              <p className='text-muted-foreground'>
                {s.city ?? 'No city'} · {s.live_ads} live ads
              </p>
              <p>{s.active ? <Badge>Public until {format(new Date(s.active_until as string), 'd MMM yyyy')}</Badge> : s.active_until ? <Badge variant='secondary'>Ended {format(new Date(s.active_until), 'd MMM yyyy')}</Badge> : <Badge variant='outline'>Not turned on yet</Badge>}</p>
            </CardContent>
            <CardFooter className='flex flex-wrap gap-2'>
              {[1, 3, 12].map((m) => (
                <Button key={m} size='sm' variant={m === 1 ? 'default' : 'outline'} disabled={activate.isPending} onClick={() => activate.mutate({ id: s.id, months: m })}>
                  +{m} {m === 1 ? 'month' : 'months'}
                </Button>
              ))}
              {s.active_until && (
                <Button size='sm' variant='ghost' disabled={activate.isPending} onClick={() => window.confirm(`Turn off the shop page of ${s.name}?`) && activate.mutate({ id: s.id, months: 0 })}>
                  Turn off
                </Button>
              )}
              {s.active && (
                <Button size='sm' variant='ghost' asChild>
                  <a href={s.url} target='_blank' rel='noopener noreferrer'>
                    <ExternalLink /> Open
                  </a>
                </Button>
              )}
            </CardFooter>
          </Card>
        ))}
      </div>

      {data && data.last_page > 1 && (
        <div className='flex items-center justify-center gap-2'>
          <Button variant='outline' size='sm' disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
          <span className='text-sm'>Page {page} of {data.last_page}</span>
          <Button variant='outline' size='sm' disabled={page >= data.last_page} onClick={() => setPage(page + 1)}>Next</Button>
        </div>
      )}
    </Page>
  )
}
