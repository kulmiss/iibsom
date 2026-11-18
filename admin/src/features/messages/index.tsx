import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format } from 'date-fns'
import { Check, Mail, RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Page } from '@/components/layout/page'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'

type Message = {
  id: number
  name: string
  email: string
  topic: string
  message: string
  language: string
  created_at: string
  handled_at: string | null
  handled_by: string | null
}
type Response = { data: Message[]; total: number; last_page: number; open: number }

export function Messages() {
  const qc = useQueryClient()
  const [show, setShow] = useState<'open' | 'done'>('open')
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['messages', show, page],
    queryFn: async () => (await api.get<Response>('/messages', { params: { show, page } })).data,
  })

  const refresh = (message: string) => {
    toast.success(message)
    qc.invalidateQueries({ queryKey: ['messages'] })
  }
  const done = useMutation({
    mutationFn: (id: number) => api.post(`/messages/${id}/done`),
    onSuccess: () => refresh('Marked as done.'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const reopen = useMutation({
    mutationFn: (id: number) => api.post(`/messages/${id}/reopen`),
    onSuccess: () => refresh('Moved back to "To answer".'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <Page title='Messages' help='messages' description='Messages from the "Contact us" form. Answer by email, then mark them done. Deleted after 180 days.'>
      <Tabs value={show} onValueChange={(v) => { setShow(v as 'open' | 'done'); setPage(1) }}>
        <TabsList>
          <TabsTrigger value='open'>To answer{data && show === 'open' ? ` (${data.open})` : ''}</TabsTrigger>
          <TabsTrigger value='done'>Done</TabsTrigger>
        </TabsList>
      </Tabs>

      {isLoading && <Skeleton className='h-64 w-full' />}
      {data && data.data.length === 0 && <p className='py-10 text-center text-muted-foreground'>Nothing here.</p>}

      <div className='grid gap-4 md:grid-cols-2'>
        {data?.data.map((m) => (
          <Card key={m.id}>
            <CardHeader>
              <CardTitle className='min-w-0 break-words text-base'>{m.topic}</CardTitle>
              <p className='min-w-0 break-words text-sm text-muted-foreground'>
                {m.name} · {m.email} · {m.language} · {format(new Date(m.created_at), 'd MMM yyyy, HH:mm')}
              </p>
            </CardHeader>
            <CardContent>
              <p className='whitespace-pre-line break-words text-sm'>{m.message}</p>
              {m.handled_at && (
                <p className='mt-3 rounded-md bg-muted p-2 text-sm'>
                  Done{m.handled_by ? ` by ${m.handled_by}` : ''} · {format(new Date(m.handled_at), 'd MMM yyyy')}
                </p>
              )}
            </CardContent>
            <CardFooter className='flex flex-wrap gap-2'>
              <Button variant='outline' asChild>
                <a href={`mailto:${encodeURIComponent(m.email)}?subject=${encodeURIComponent('iibsom: ' + m.topic)}`}>
                  <Mail /> Answer by email
                </a>
              </Button>
              {m.handled_at ? (
                <Button variant='outline' onClick={() => reopen.mutate(m.id)} disabled={reopen.isPending}>
                  <RotateCcw /> Not done
                </Button>
              ) : (
                <Button onClick={() => done.mutate(m.id)} disabled={done.isPending}>
                  <Check /> Done
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
