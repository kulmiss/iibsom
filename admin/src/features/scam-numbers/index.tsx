import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format, formatDistanceToNow } from 'date-fns'
import { Check, Plus, ShieldAlert, X } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Page } from '@/components/layout/page'
import { ReasonDialog } from '@/components/reason-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'

type Num = { id: number; phone: string; reason: string; proof: string | null; marked_by: string | null; created_at: string; removed_at: string | null; removed_reason: string | null; waiting_appeals: number; ads_waiting: number }
type Appeal = { id: number; phone: string; listed_reason: string; still_listed: boolean; message: string; contact: string | null; status: 'waiting' | 'accepted' | 'refused'; decision_note: string | null; decided_by: string | null; created_at: string; decided_at: string | null }
type Tab = 'listed' | 'removed' | 'appeals' | 'decided'

/** Admin → Scam numbers (PR 109): staff mark numbers with proof; never automatic. Buyers only ever see "Reported for scams". */
export function ScamNumbers() {
  const qc = useQueryClient()
  const [tab, setTab] = useState<Tab>('listed')
  const [search, setSearch] = useState('')
  const [adding, setAdding] = useState(false)
  const [form, setForm] = useState({ phone: '', reason: '', proof: '' })
  const [removing, setRemoving] = useState<Num | null>(null)
  const [deciding, setDeciding] = useState<{ appeal: Appeal; accept: boolean } | null>(null)
  const numbersTab = tab === 'listed' || tab === 'removed'

  const numbers = useQuery({
    queryKey: ['scam-numbers', tab, search],
    queryFn: async () => (await api.get<{ data: Num[]; total: number; waiting_appeals: number }>('/scam-numbers', { params: { status: tab === 'removed' ? 'removed' : 'listed', search } })).data,
    enabled: numbersTab,
  })
  const appeals = useQuery({
    queryKey: ['scam-appeals', tab],
    queryFn: async () => (await api.get<{ data: Appeal[]; total: number }>('/scam-appeals', { params: { status: tab === 'appeals' ? 'waiting' : 'accepted' } })).data,
    enabled: !numbersTab,
  })
  const refusedToo = useQuery({
    queryKey: ['scam-appeals', 'refused'],
    queryFn: async () => (await api.get<{ data: Appeal[] }>('/scam-appeals', { params: { status: 'refused' } })).data,
    enabled: tab === 'decided',
  })
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['scam-numbers'] })
    qc.invalidateQueries({ queryKey: ['scam-appeals'] })
    qc.invalidateQueries({ queryKey: ['ads'] })
  }
  const add = useMutation({
    mutationFn: () => api.post<Num & { ads_to_review: number }>('/scam-numbers', form),
    onSuccess: (res) => {
      toast.success(res.data.ads_to_review ? `Marked. ${res.data.ads_to_review} live ads went back to “Waiting for approval”.` : 'Marked “Reported for scams”.')
      setAdding(false)
      setForm({ phone: '', reason: '', proof: '' })
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const remove = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/scam-numbers/${id}/remove`, { reason }),
    onSuccess: () => {
      toast.success('Taken off the list.')
      setRemoving(null)
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const decide = useMutation({
    mutationFn: ({ id, accept, note }: { id: number; accept: boolean; note: string }) => api.post(`/scam-appeals/${id}/decide`, { accept, note }),
    onSuccess: (_, v) => {
      toast.success(v.accept ? 'Accepted: the number is off the list.' : 'Refused: the number stays on the list.')
      setDeciding(null)
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const waiting = numbers.data?.waiting_appeals ?? 0
  const appealList = tab === 'decided' ? [...(appeals.data?.data ?? []), ...(refusedToo.data?.data ?? [])] : (appeals.data?.data ?? [])

  return (
    <Page
      title='Scam numbers'
      help='scam-numbers'
      description='Numbers your team checked and marked “Reported for scams”. They cannot post, their ads wait for you, and buyers who search or check them see a warning (never a name).'
      actions={
        <Button onClick={() => setAdding(true)} data-scam-add>
          <Plus /> Mark a number
        </Button>
      }
    >
      <Tabs value={tab} onValueChange={(v) => setTab(v as Tab)}>
        <TabsList>
          <TabsTrigger value='listed'>On the list</TabsTrigger>
          <TabsTrigger value='appeals'>Review requests {waiting > 0 && <Badge className='ms-1'>{waiting}</Badge>}</TabsTrigger>
          <TabsTrigger value='decided'>Decided</TabsTrigger>
          <TabsTrigger value='removed'>Taken off</TabsTrigger>
        </TabsList>
      </Tabs>

      {numbersTab && <Input placeholder='Find a number or reason…' value={search} onChange={(e) => setSearch(e.target.value)} className='h-9 w-full sm:w-72' aria-label='Find a number' />}
      {(numbers.isLoading || appeals.isLoading) && <Skeleton className='h-48 w-full' />}

      {numbersTab && numbers.data && (
        numbers.data.data.length === 0 ? (
          <p className='py-10 text-center text-muted-foreground'>Nothing here.</p>
        ) : (
          <div className='grid gap-3 md:grid-cols-2'>
            {numbers.data.data.map((n) => (
              <Card key={n.id} data-scam-number={n.phone}>
                <CardHeader>
                  <CardTitle className='flex flex-wrap items-center gap-2 font-mono text-base'>
                    <ShieldAlert className='size-4 text-destructive' /> {n.phone}
                    {n.waiting_appeals > 0 && <Badge variant='secondary'>review requested</Badge>}
                  </CardTitle>
                  <p className='text-xs text-muted-foreground'>
                    Marked {formatDistanceToNow(new Date(n.created_at), { addSuffix: true })} by {n.marked_by ?? 'staff'}
                    {n.removed_at && ` · taken off ${format(new Date(n.removed_at), 'd MMM yyyy')}`}
                  </p>
                </CardHeader>
                <CardContent className='grid gap-2 text-sm'>
                  <p>
                    <span className='font-medium'>Reason:</span> {n.reason}
                  </p>
                  {n.proof && (
                    <p className='rounded-md bg-muted p-2 text-xs'>
                      <span className='font-medium'>Proof (staff only):</span> {n.proof}
                    </p>
                  )}
                  {n.removed_reason && <p className='text-muted-foreground'>Taken off: {n.removed_reason}</p>}
                  {!n.removed_at && n.ads_waiting > 0 && <p className='text-muted-foreground'>{n.ads_waiting} of their ads wait in “Waiting for approval”.</p>}
                </CardContent>
                {!n.removed_at && (
                  <CardFooter>
                    <Button variant='outline' size='sm' onClick={() => setRemoving(n)}>
                      <X /> Take off the list
                    </Button>
                  </CardFooter>
                )}
              </Card>
            ))}
          </div>
        )
      )}

      {!numbersTab && appeals.data && (
        appealList.length === 0 ? (
          <p className='py-10 text-center text-muted-foreground'>No review requests.</p>
        ) : (
          <div className='grid gap-3 md:grid-cols-2'>
            {appealList.map((a) => (
              <Card key={a.id} data-scam-appeal={a.id}>
                <CardHeader>
                  <CardTitle className='flex flex-wrap items-center gap-2 font-mono text-base'>
                    {a.phone} {a.status !== 'waiting' && <Badge variant={a.status === 'accepted' ? 'default' : 'secondary'}>{a.status}</Badge>}
                  </CardTitle>
                  <p className='text-xs text-muted-foreground'>Sent {formatDistanceToNow(new Date(a.created_at), { addSuffix: true })} · listed for: {a.listed_reason}</p>
                </CardHeader>
                <CardContent className='grid gap-2 text-sm'>
                  <p className='whitespace-pre-line rounded-md bg-muted p-2'>{a.message}</p>
                  <p className='text-muted-foreground'>Answer to: {a.contact ?? 'no contact given'}</p>
                  {a.decision_note && (
                    <p>
                      Decision by {a.decided_by ?? 'staff'}: {a.decision_note}
                    </p>
                  )}
                </CardContent>
                {a.status === 'waiting' && (
                  <CardFooter className='flex flex-wrap gap-2'>
                    <Button size='sm' onClick={() => setDeciding({ appeal: a, accept: true })}>
                      <Check /> Accept (take off the list)
                    </Button>
                    <Button size='sm' variant='outline' onClick={() => setDeciding({ appeal: a, accept: false })}>
                      <X /> Refuse
                    </Button>
                  </CardFooter>
                )}
              </Card>
            ))}
          </div>
        )
      )}

      <Dialog open={adding} onOpenChange={setAdding}>
        <DialogContent className='sm:max-w-md'>
          <DialogHeader>
            <DialogTitle>Mark a number “Reported for scams”</DialogTitle>
            <DialogDescription>Only after your team checked the reports. Buyers see the number and “Reported for scams”, never a name. Its live ads go back to waiting.</DialogDescription>
          </DialogHeader>
          <div className='grid gap-3'>
            <div className='grid gap-1.5'>
              <Label htmlFor='scam-phone'>Phone number</Label>
              <Input id='scam-phone' type='tel' placeholder='61 5123456' value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
            </div>
            <div className='grid gap-1.5'>
              <Label htmlFor='scam-reason'>Reason (short)</Label>
              <Input id='scam-reason' maxLength={300} placeholder='Took a deposit and never delivered' value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} />
            </div>
            <div className='grid gap-1.5'>
              <Label htmlFor='scam-proof'>Proof (staff only)</Label>
              <Textarea id='scam-proof' maxLength={2000} rows={3} placeholder='Report numbers, what the buyers sent, payment numbers…' value={form.proof} onChange={(e) => setForm({ ...form, proof: e.target.value })} />
            </div>
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setAdding(false)}>
              Cancel
            </Button>
            <Button variant='destructive' disabled={add.isPending || form.phone.trim() === '' || form.reason.trim().length < 3 || form.proof.trim().length < 3} onClick={() => add.mutate()} data-scam-save>
              Mark number
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ReasonDialog
        open={removing !== null}
        onOpenChange={(o) => !o && setRemoving(null)}
        title={`Take ${removing?.phone ?? ''} off the list?`}
        description='It can post again. Ads that are waiting stay waiting until you approve them.'
        confirmText='Take off the list'
        presets={['Marked by mistake', 'Problem solved with the buyer', 'Number now belongs to someone else']}
        busy={remove.isPending}
        onConfirm={(reason) => removing && remove.mutate({ id: removing.id, reason })}
      />
      <ReasonDialog
        open={deciding !== null}
        onOpenChange={(o) => !o && setDeciding(null)}
        title={deciding?.accept ? `Accept the review for ${deciding.appeal.phone}?` : `Refuse the review for ${deciding?.appeal.phone ?? ''}?`}
        description={deciding?.accept ? 'The number comes off the list.' : 'The number stays on the list.'}
        label='Note (logged)'
        confirmText={deciding?.accept ? 'Accept' : 'Refuse'}
        busy={decide.isPending}
        onConfirm={(note) => deciding && decide.mutate({ id: deciding.appeal.id, accept: deciding.accept, note })}
      />
    </Page>
  )
}
