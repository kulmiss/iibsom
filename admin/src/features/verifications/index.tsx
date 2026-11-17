import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format, formatDistanceToNow } from 'date-fns'
import { BadgeCheck, X } from 'lucide-react'
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
type Verification = {
  id: number
  status: Status
  id_type: 'national_id' | 'passport' | 'driving_licence'
  has_photo: boolean
  reason: string | null
  created_at: string
  reviewed_at: string | null
  reviewer: string | null
  user: { id: number; name: string; email: string; phone: string | null; verified: boolean }
}
type Response = { data: Verification[]; total: number; last_page: number; pending: number }

const ID_TYPE = { national_id: 'National ID card', passport: 'Passport', driving_licence: 'Driving licence' } as const

export function Verifications() {
  const qc = useQueryClient()
  const [status, setStatus] = useState<Status>('pending')
  const [page, setPage] = useState(1)
  const [rejecting, setRejecting] = useState<Verification | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['verifications', status, page],
    queryFn: async () => (await api.get<Response>('/verifications', { params: { status, page } })).data,
  })

  const done = (message: string) => {
    toast.success(message)
    setRejecting(null)
    qc.invalidateQueries({ queryKey: ['verifications'] })
    qc.invalidateQueries({ queryKey: ['users'] })
  }
  const approve = useMutation({
    mutationFn: (id: number) => api.post(`/verifications/${id}/approve`),
    onSuccess: () => done('Verified badge given. The ID photo was deleted.'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const reject = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/verifications/${id}/reject`, { reason }),
    onSuccess: () => done('Not verified. The seller sees your reason. The ID photo was deleted.'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <Page title='ID checks' help='verifications' description='Check that the name and face on the ID match the seller. The photo is deleted as soon as you decide.'>
      <Tabs value={status} onValueChange={(v) => { setStatus(v as Status); setPage(1) }}>
        <TabsList>
          <TabsTrigger value='pending'>Waiting {data && status === 'pending' ? `(${data.pending})` : ''}</TabsTrigger>
          <TabsTrigger value='approved'>Verified</TabsTrigger>
          <TabsTrigger value='rejected'>Not verified</TabsTrigger>
        </TabsList>
      </Tabs>

      {isLoading && <Skeleton className='h-64 w-full' />}
      {data && data.data.length === 0 && <p className='py-10 text-center text-muted-foreground'>Nothing here.</p>}

      <div className='grid gap-4 md:grid-cols-2'>
        {data?.data.map((v) => (
          <Card key={v.id}>
            <CardHeader>
              <CardTitle className='flex flex-wrap items-center gap-2 text-base'>
                {v.user.name} {v.user.verified && <Badge>Verified</Badge>}
              </CardTitle>
              <p className='text-sm text-muted-foreground'>
                {v.user.email} · {v.user.phone ?? 'no phone'} · {ID_TYPE[v.id_type]}
              </p>
              <p className='text-xs text-muted-foreground'>
                Sent {formatDistanceToNow(new Date(v.created_at), { addSuffix: true })}
                {v.reviewed_at && ` · decided ${format(new Date(v.reviewed_at), 'd MMM yyyy')} by ${v.reviewer ?? 'staff'}`}
              </p>
            </CardHeader>
            <CardContent>
              {v.has_photo ? (
                <a href={`/admin/api/verifications/${v.id}/photo`} target='_blank' rel='noopener noreferrer'>
                  <img src={`/admin/api/verifications/${v.id}/photo`} alt={`ID photo of ${v.user.name}`} className='max-h-72 w-full rounded-md border object-contain' loading='lazy' />
                </a>
              ) : (
                <p className='text-sm text-muted-foreground'>The ID photo was deleted after the decision.</p>
              )}
              {v.reason && <p className='mt-3 rounded-md bg-muted p-2 text-sm'>Reason: {v.reason}</p>}
            </CardContent>
            {v.status === 'pending' && (
              <CardFooter className='flex flex-wrap gap-2'>
                <Button onClick={() => approve.mutate(v.id)} disabled={approve.isPending}>
                  <BadgeCheck /> Give Verified badge
                </Button>
                <Button variant='outline' onClick={() => setRejecting(v)}>
                  <X /> Not verified
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
        title={`Not verify ${rejecting?.user.name ?? ''}?`}
        description='The seller sees your reason and can send a new photo.'
        confirmText='Not verified'
        presets={['Photo is blurry', 'Name does not match the account', 'Not an ID card', 'Part of the ID is hidden']}
        busy={reject.isPending}
        onConfirm={(reason) => rejecting && reject.mutate({ id: rejecting.id, reason })}
      />
    </Page>
  )
}
