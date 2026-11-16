import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { format, formatDistanceToNow } from 'date-fns'
import { Check, Pencil, Trash2, X } from 'lucide-react'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Separator } from '@/components/ui/separator'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { ReasonDialog } from '@/components/reason-dialog'
import { type AdDetail } from '../types'
import { REJECT_PRESETS, useAdActions } from '../use-ad-actions'
import { AdChanges } from './ad-changes'
import { EditedBadge, StatusBadge } from './status-badge'

/** Everything about one ad + approve / reject / edit / delete. */
export function AdSheet({ adId, onOpenChange }: { adId: number | null; onOpenChange: (open: boolean) => void }) {
  const can = useAuthStore((s) => s.can)
  const actions = useAdActions()
  const [rejecting, setRejecting] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [editing, setEditing] = useState(false)

  const { data: ad, isLoading } = useQuery({
    queryKey: ['ad', adId],
    queryFn: async () => (await api.get<AdDetail>(`/ads/${adId}`)).data,
    enabled: adId !== null,
  })

  return (
    <Sheet open={adId !== null} onOpenChange={(o) => { setEditing(false); onOpenChange(o) }}>
      <SheetContent className='flex w-full flex-col gap-0 p-0 sm:max-w-xl'>
        <SheetHeader className='border-b'>
          <SheetTitle className='pe-6'>{ad ? ad.title : 'Ad'}</SheetTitle>
          <SheetDescription className='flex flex-wrap items-center gap-2'>
            {ad && <StatusBadge status={ad.status} label={ad.status_label} />}
            {ad?.edited && <EditedBadge />}
            {ad && <span>#{ad.id} · {ad.category} · {ad.city}{ad.area ? `, ${ad.area}` : ''}</span>}
            {ad?.is_demo && <Badge variant='secondary'>Demo</Badge>}
          </SheetDescription>
        </SheetHeader>

        <ScrollArea className='min-h-0 flex-1'>
          {isLoading || !ad ? (
            <div className='space-y-3 p-4'>
              <Skeleton className='h-48' />
              <Skeleton className='h-6 w-1/2' />
              <Skeleton className='h-24' />
            </div>
          ) : editing ? (
            <EditForm ad={ad} onDone={() => setEditing(false)} />
          ) : (
            <div className='space-y-5 p-4'>
              {ad.changes && <AdChanges changes={ad.changes} />}

              {ad.photos.length > 0 ? (
                <div className='grid grid-cols-3 gap-2'>
                  {ad.photos.map((p, i) => (
                    <a key={p.id} href={p.large ?? '#'} target='_blank' rel='noreferrer' className='relative aspect-square overflow-hidden rounded-md bg-muted'>
                      {p.thumb ? <img src={p.thumb} alt={`Photo ${i + 1}`} loading='lazy' className='size-full object-cover' /> : <span className='flex size-full items-center justify-center text-xs text-muted-foreground'>{p.status}</span>}
                    </a>
                  ))}
                </div>
              ) : (
                <p className='rounded-md bg-muted p-4 text-center text-sm text-muted-foreground'>No photos</p>
              )}

              <div className='flex items-baseline justify-between gap-2'>
                <p className='text-2xl font-bold'>{ad.price || '–'}</p>
                {ad.condition && <Badge variant='outline'>{ad.condition === 'new' ? 'New' : 'Used'}</Badge>}
              </div>

              {ad.rejection_reason && (
                <p className='rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm'>
                  <span className='font-medium'>Rejected:</span> {ad.rejection_reason}
                </p>
              )}

              {ad.fields.length > 0 && (
                <dl className='grid grid-cols-2 gap-x-4 gap-y-2 text-sm'>
                  {ad.fields.map((f) => (
                    <div key={f.label}>
                      <dt className='text-muted-foreground'>{f.label}</dt>
                      <dd className='font-medium'>{f.value}</dd>
                    </div>
                  ))}
                </dl>
              )}

              <p className='text-sm whitespace-pre-line'>{ad.description}</p>

              <Separator />

              <div className='text-sm'>
                <p className='mb-1 font-medium'>Seller</p>
                <p>
                  {can('users.view') ? (
                    <Link to='/users' search={{ open: ad.seller_info.id }} className='underline underline-offset-4'>
                      {ad.seller_info.name}
                    </Link>
                  ) : (
                    ad.seller_info.name
                  )}{' '}
                  {ad.seller_info.banned && <Badge variant='destructive'>Banned</Badge>}
                </p>
                <p className='text-muted-foreground'>
                  {ad.seller_info.phone ?? 'No phone'} · {ad.seller_info.city ?? '–'} · member since {format(new Date(ad.seller_info.member_since), 'MMM yyyy')} · {ad.seller_info.ads_total} ads
                </p>
              </div>

              <div className='text-xs text-muted-foreground'>
                Posted {formatDistanceToNow(new Date(ad.created_at), { addSuffix: true })}
                {ad.reviewed_by && ` · checked by ${ad.reviewed_by}`}
                {ad.expires_at && ` · ends ${format(new Date(ad.expires_at), 'd MMM yyyy')}`}
              </div>

              {ad.history.length > 0 && (
                <div>
                  <p className='mb-1 text-sm font-medium'>History</p>
                  <ul className='space-y-1 text-xs text-muted-foreground'>
                    {ad.history.map((h, i) => (
                      <li key={i}>
                        {format(new Date(h.at), 'd MMM HH:mm')} · {h.by ?? 'System'}: {h.summary}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          )}
        </ScrollArea>

        {ad && !editing && (
          <div className='flex flex-wrap gap-2 border-t p-3'>
            {can('ads.moderate') && (ad.status === 'pending' || ad.status === 'rejected') && (
              <Button onClick={() => actions.approve.mutate(ad.id)} disabled={actions.approve.isPending}>
                <Check /> Approve
              </Button>
            )}
            {can('ads.moderate') && ad.status !== 'rejected' && (
              <Button variant='outline' onClick={() => setRejecting(true)}>
                <X /> Reject
              </Button>
            )}
            {can('ads.edit') && (
              <Button variant='outline' onClick={() => setEditing(true)}>
                <Pencil /> Edit
              </Button>
            )}
            {can('ads.delete') && (
              <Button variant='ghost' className='ms-auto text-destructive' onClick={() => setDeleting(true)}>
                <Trash2 /> Delete
              </Button>
            )}
          </div>
        )}

        {ad && (
          <>
            <ReasonDialog
              open={rejecting}
              onOpenChange={setRejecting}
              title='Reject this ad?'
              description='The seller will see the reason and can fix the ad.'
              confirmText='Reject ad'
              destructive
              presets={REJECT_PRESETS}
              busy={actions.reject.isPending}
              onConfirm={(reason) => actions.reject.mutate({ id: ad.id, reason }, { onSuccess: () => setRejecting(false) })}
            />
            <ConfirmDialog
              open={deleting}
              onOpenChange={setDeleting}
              title='Delete this ad?'
              desc='It disappears from the site. The data is kept for records.'
              confirmText='Delete'
              destructive
              handleConfirm={() => actions.remove.mutate(ad.id, { onSuccess: () => { setDeleting(false); onOpenChange(false) } })}
            />
          </>
        )}
      </SheetContent>
    </Sheet>
  )
}

function EditForm({ ad, onDone }: { ad: AdDetail; onDone: () => void }) {
  const { update } = useAdActions()
  const priceNumber = Number(ad.price.replace(/[^0-9]/g, '')) || null
  const [title, setTitle] = useState(ad.title)
  const [description, setDescription] = useState(ad.description)
  const [price, setPrice] = useState<string>(priceNumber ? String(priceNumber) : '')
  const [negotiable, setNegotiable] = useState(ad.price.includes('Negotiable'))

  return (
    <form
      className='space-y-4 p-4'
      onSubmit={(e) => {
        e.preventDefault()
        update.mutate({ id: ad.id, title, description, price: price ? Number(price) : null, price_negotiable: negotiable }, { onSuccess: onDone })
      }}
    >
      <div className='grid gap-2'>
        <Label htmlFor='ad-title'>Title</Label>
        <Input id='ad-title' value={title} onChange={(e) => setTitle(e.target.value)} maxLength={80} />
      </div>
      <div className='grid gap-2'>
        <Label htmlFor='ad-desc'>Description</Label>
        <Textarea id='ad-desc' rows={8} value={description} onChange={(e) => setDescription(e.target.value)} maxLength={3000} />
      </div>
      <div className='flex items-end gap-4'>
        <div className='grid flex-1 gap-2'>
          <Label htmlFor='ad-price'>Price (USD)</Label>
          <Input id='ad-price' type='number' min={0} value={price} onChange={(e) => setPrice(e.target.value)} />
        </div>
        <label className='flex items-center gap-2 pb-2 text-sm'>
          <Switch checked={negotiable} onCheckedChange={setNegotiable} /> Negotiable
        </label>
      </div>
      <div className='flex gap-2'>
        <Button type='submit' disabled={update.isPending}>
          Save
        </Button>
        <Button type='button' variant='outline' onClick={onDone}>
          Cancel
        </Button>
      </div>
    </form>
  )
}
