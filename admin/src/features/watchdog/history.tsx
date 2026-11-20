import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { AdSheet } from '@/features/ads/components/ad-sheet'
import { ProblemRow } from './problem-row'
import { type Problem } from './types'

type HistoryPage = {
  data: Problem[]
  meta: { current_page: number; last_page: number; total: number }
}

/** The last 30 days (older ones are deleted every night), newest first, with filters. */
export function History({ areas }: { areas: Record<string, string> }) {
  const [level, setLevel] = useState('all')
  const [area, setArea] = useState('all')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [openAd, setOpenAd] = useState<number | null>(null)
  const params = {
    level: level === 'all' ? undefined : level,
    area: area === 'all' ? undefined : area,
    q: q || undefined,
    page,
  }
  const { data, isLoading } = useQuery({
    queryKey: ['watchdog', 'history', params],
    queryFn: async () => (await api.get<HistoryPage>('/watchdog/history', { params })).data,
    placeholderData: keepPreviousData,
  })

  return (
    <div className='grid gap-3'>
      <div className='flex flex-wrap gap-2'>
        <Input value={q} onChange={(e) => (setQ(e.target.value), setPage(1))} placeholder='Search titles…' className='w-full sm:w-56' aria-label='Search titles' />
        <Select value={level} onValueChange={(v) => (setLevel(v), setPage(1))}>
          <SelectTrigger className='w-36' aria-label='Level'>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>All levels</SelectItem>
            <SelectItem value='urgent'>🔴 Urgent</SelectItem>
            <SelectItem value='warning'>🟠 Warning</SelectItem>
            <SelectItem value='info'>🟢 Info</SelectItem>
          </SelectContent>
        </Select>
        <Select value={area} onValueChange={(v) => (setArea(v), setPage(1))}>
          <SelectTrigger className='w-40' aria-label='Area'>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>All areas</SelectItem>
            {Object.entries(areas).map(([key, label]) => (
              <SelectItem key={key} value={key}>
                {label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <Card>
        <CardContent className='divide-y'>
          {isLoading && <Skeleton className='h-48' />}
          {data?.data.length === 0 && <p className='text-sm text-muted-foreground'>Nothing in the last 30 days.</p>}
          {data?.data.map((p) => (
            <ProblemRow key={p.id} problem={p} onOpenAd={setOpenAd} />
          ))}
        </CardContent>
      </Card>
      {data && data.meta.last_page > 1 && (
        <div className='flex items-center justify-between gap-2 text-sm'>
          <span className='text-muted-foreground'>
            Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} in all
          </span>
          <div className='flex gap-2'>
            <Button size='sm' variant='outline' disabled={page <= 1} onClick={() => setPage(page - 1)}>
              Previous
            </Button>
            <Button size='sm' variant='outline' disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>
              Next
            </Button>
          </div>
        </div>
      )}
      <AdSheet adId={openAd} onOpenChange={(o) => !o && setOpenAd(null)} />
    </div>
  )
}
