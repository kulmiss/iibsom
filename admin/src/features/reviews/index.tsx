import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format } from 'date-fns'
import { Eye, EyeOff, Star } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Page } from '@/components/layout/page'
import { ReasonDialog } from '@/components/reason-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'

type Review = {
  id: number
  stars: number
  comment: string | null
  created_at: string
  hidden_at: string | null
  hidden_reason: string | null
  seller: { id: number; name: string }
  buyer: { id: number; name: string }
  ad: { id: number; title: string } | null
}
type Response = { data: Review[]; total: number; last_page: number }

function Stars({ count }: { count: number }) {
  return (
    <span className='inline-flex' role='img' aria-label={`${count} of 5 stars`}>
      {[1, 2, 3, 4, 5].map((i) => (
        <Star key={i} className={i <= count ? 'size-4 fill-amber-400 text-amber-500' : 'size-4 text-muted-foreground/40'} />
      ))}
    </span>
  )
}

export function Reviews() {
  const qc = useQueryClient()
  const [show, setShow] = useState<'visible' | 'hidden'>('visible')
  const [stars, setStars] = useState('all')
  const [page, setPage] = useState(1)
  const [hiding, setHiding] = useState<Review | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['reviews', show, stars, page],
    queryFn: async () => (await api.get<Response>('/reviews', { params: { show, stars: stars === 'all' ? undefined : stars, page } })).data,
  })

  const done = (message: string) => {
    toast.success(message)
    setHiding(null)
    qc.invalidateQueries({ queryKey: ['reviews'] })
  }
  const hide = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/reviews/${id}/hide`, { reason }),
    onSuccess: () => done('Rating hidden. The seller\'s stars were counted again.'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const unhide = useMutation({
    mutationFn: (id: number) => api.post(`/reviews/${id}/unhide`),
    onSuccess: () => done('Rating shown again.'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <Page title='Ratings' help='reviews' description='Stars buyers gave sellers after a chat. Hide a rating that is rude, fake or about something else.'>
      <div className='flex flex-wrap items-center gap-3'>
        <Tabs value={show} onValueChange={(v) => { setShow(v as 'visible' | 'hidden'); setPage(1) }}>
          <TabsList>
            <TabsTrigger value='visible'>Shown</TabsTrigger>
            <TabsTrigger value='hidden'>Hidden</TabsTrigger>
          </TabsList>
        </Tabs>
        <Select value={stars} onValueChange={(v) => { setStars(v); setPage(1) }}>
          <SelectTrigger className='w-40' aria-label='Filter by stars'>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>All stars</SelectItem>
            {[1, 2, 3, 4, 5].map((n) => (
              <SelectItem key={n} value={String(n)}>{n} {n === 1 ? 'star' : 'stars'}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {isLoading && <Skeleton className='h-64 w-full' />}
      {data && data.data.length === 0 && <p className='py-10 text-center text-muted-foreground'>Nothing here.</p>}

      <div className='grid gap-4 md:grid-cols-2'>
        {data?.data.map((r) => (
          <Card key={r.id}>
            <CardHeader>
              <CardTitle className='flex flex-wrap items-center gap-2 text-base'>
                <Stars count={r.stars} /> <span className='min-w-0 break-words'>for {r.seller.name}</span>
              </CardTitle>
              <p className='text-sm text-muted-foreground'>
                From {r.buyer.name} · {format(new Date(r.created_at), 'd MMM yyyy')}
                {r.ad && <> · about “{r.ad.title}”</>}
              </p>
            </CardHeader>
            <CardContent>
              {r.comment ? <p className='whitespace-pre-line break-words text-sm'>{r.comment}</p> : <p className='text-sm text-muted-foreground'>No comment.</p>}
              {r.hidden_reason && <p className='mt-3 rounded-md bg-muted p-2 text-sm'>Hidden: {r.hidden_reason}</p>}
            </CardContent>
            <CardFooter>
              {r.hidden_at ? (
                <Button variant='outline' onClick={() => unhide.mutate(r.id)} disabled={unhide.isPending}>
                  <Eye /> Show again
                </Button>
              ) : (
                <Button variant='outline' onClick={() => setHiding(r)}>
                  <EyeOff /> Hide
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

      <ReasonDialog
        open={hiding !== null}
        onOpenChange={(o) => !o && setHiding(null)}
        title={`Hide this rating of ${hiding?.seller.name ?? ''}?`}
        description='It disappears from the website and the stars are counted again. The buyer is told it was hidden.'
        confirmText='Hide'
        destructive
        presets={['Rude words', 'Fake or paid rating', 'About something else', 'Personal details in the comment']}
        busy={hide.isPending}
        onConfirm={(reason) => hiding && hide.mutate({ id: hiding.id, reason })}
      />
    </Page>
  )
}
