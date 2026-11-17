import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format, formatDistanceToNow } from 'date-fns'
import { Check, X } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Page } from '@/components/layout/page'
import { ReasonDialog } from '@/components/reason-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'

type Status = 'pending' | 'approved' | 'rejected'
type PaymentRow = {
  id: number
  status: Status
  package: 'top' | 'vip' | 'diamond'
  method_label: string
  amount: string
  reference: string
  is_test: boolean
  has_receipt: boolean
  reason: string | null
  created_at: string
  reviewed_at: string | null
  reviewer: string | null
  ad: { id: number; title: string } | null
  user: { id: number; name: string; phone: string | null; email: string }
}
type ListResponse = { data: PaymentRow[]; total: number; last_page: number; pending: number; test_mode: boolean }
type Summary = { real: Record<string, number>; test: Record<string, number>; unbalanced: number }

const PACKAGE = { top: 'Top', vip: 'VIP', diamond: 'Diamond' } as const
const money = (cents: number) => `${cents < 0 ? '−' : ''}$${(Math.abs(cents) / 100).toFixed(2)}`

export function Payments() {
  const qc = useQueryClient()
  const [status, setStatus] = useState<Status>('pending')
  const [page, setPage] = useState(1)
  const [rejecting, setRejecting] = useState<PaymentRow | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['payments', status, page],
    queryFn: async () => (await api.get<ListResponse>('/payments', { params: { status, page } })).data,
  })
  const { data: summary } = useQuery({ queryKey: ['payments-summary'], queryFn: async () => (await api.get<Summary>('/payments/summary')).data })

  const done = (message: string) => {
    toast.success(message)
    setRejecting(null)
    qc.invalidateQueries({ queryKey: ['payments'] })
    qc.invalidateQueries({ queryKey: ['payments-summary'] })
  }
  const approve = useMutation({
    mutationFn: (id: number) => api.post(`/payments/${id}/approve`),
    onSuccess: () => done('Payment approved. The boost has started.'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const reject = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/payments/${id}/reject`, { reason }),
    onSuccess: () => done('Payment rejected. The seller sees your reason.'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <Page title='Payments' help='payments' description='Boost payments sent by mobile money. Check the receipt and that the money really arrived, then approve.'>
      {data?.test_mode && <p className='rounded-md bg-amber-100 p-3 text-sm font-medium text-amber-950 dark:bg-amber-900/40 dark:text-amber-100'>TEST MODE is ON (Settings → General). New payments are test payments and stay out of the real books.</p>}
      {summary && summary.unbalanced > 0 && <p className='rounded-md bg-destructive/10 p-3 text-sm font-medium text-destructive'>The books do not balance ({summary.unbalanced} transaction). Run iibsom:ledger:check and contact the developer.</p>}

      {summary && (
        <div className='grid gap-3 sm:grid-cols-2'>
          {(['real', 'test'] as const).map((kind) => (
            <Card key={kind}>
              <CardHeader className='pb-2'>
                <CardTitle className='text-sm'>{kind === 'real' ? 'Real money (all time)' : 'Test money (all time)'}</CardTitle>
              </CardHeader>
              <CardContent>
                {Object.keys(summary[kind]).length === 0 ? (
                  <p className='text-sm text-muted-foreground'>Nothing yet.</p>
                ) : (
                  <dl className='grid grid-cols-2 gap-1 text-sm'>
                    {Object.entries(summary[kind]).map(([account, cents]) => (
                      <div key={account} className='contents'>
                        <dt className='text-muted-foreground'>{account}</dt>
                        <dd className='text-end font-mono'>{money(account.startsWith('revenue:') ? -cents : cents)}</dd>
                      </div>
                    ))}
                  </dl>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Tabs value={status} onValueChange={(v) => { setStatus(v as Status); setPage(1) }}>
        <TabsList>
          <TabsTrigger value='pending'>Waiting {data && status === 'pending' ? `(${data.pending})` : ''}</TabsTrigger>
          <TabsTrigger value='approved'>Approved</TabsTrigger>
          <TabsTrigger value='rejected'>Rejected</TabsTrigger>
        </TabsList>
      </Tabs>

      {isLoading && <Skeleton className='h-48 w-full' />}
      {data && data.data.length === 0 && <p className='py-10 text-center text-muted-foreground'>Nothing here.</p>}

      <div className='grid gap-4 md:grid-cols-2'>
        {data?.data.map((p) => (
          <Card key={p.id}>
            <CardHeader>
              <CardTitle className='flex flex-wrap items-center gap-2 text-base'>
                {p.amount} · {PACKAGE[p.package]} {p.is_test && <Badge variant='secondary'>Test</Badge>}
              </CardTitle>
              <p className='text-sm'>
                {p.method_label} · <span className='font-mono'>{p.reference}</span>
              </p>
              <p className='text-sm text-muted-foreground'>
                {p.user.name} · {p.user.phone ?? p.user.email}
              </p>
              <p className='truncate text-sm text-muted-foreground'>Ad: {p.ad?.title ?? '(deleted)'}</p>
              <p className='text-xs text-muted-foreground'>
                Sent {formatDistanceToNow(new Date(p.created_at), { addSuffix: true })}
                {p.reviewed_at && ` · decided ${format(new Date(p.reviewed_at), 'd MMM yyyy')} by ${p.reviewer ?? 'staff'}`}
              </p>
            </CardHeader>
            <CardContent>
              {p.has_receipt && (
                <a href={`/admin/api/payments/${p.id}/receipt`} target='_blank' rel='noopener noreferrer'>
                  <img src={`/admin/api/payments/${p.id}/receipt`} alt={`Receipt for payment ${p.id}`} className='max-h-72 w-full rounded-md border object-contain' loading='lazy' />
                </a>
              )}
              {p.reason && <p className='mt-3 rounded-md bg-muted p-2 text-sm'>Reason: {p.reason}</p>}
            </CardContent>
            {p.status === 'pending' && (
              <CardFooter className='flex flex-wrap gap-2'>
                <Button onClick={() => window.confirm(`Approve ${p.amount}? Only if the money arrived in your ${p.method_label} account.`) && approve.mutate(p.id)} disabled={approve.isPending}>
                  <Check /> Approve
                </Button>
                <Button variant='outline' onClick={() => setRejecting(p)}>
                  <X /> Reject
                </Button>
              </CardFooter>
            )}
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
        open={rejecting !== null}
        onOpenChange={(o) => !o && setRejecting(null)}
        title={`Reject payment of ${rejecting?.amount ?? ''}?`}
        description='The seller sees your reason. No boost starts.'
        confirmText='Reject'
        presets={['Money did not arrive', 'Amount is wrong', 'Receipt is not clear', 'Transaction ID does not match']}
        busy={reject.isPending}
        onConfirm={(reason) => rejecting && reject.mutate({ id: rejecting.id, reason })}
      />
    </Page>
  )
}
