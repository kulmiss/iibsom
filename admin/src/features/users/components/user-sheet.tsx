import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format } from 'date-fns'
import { Ban, BadgeX, RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { ReasonDialog } from '@/components/reason-dialog'
import { AdsTable } from '@/features/ads/components/ads-table'
import { type UserDetail } from '../types'

export function UserSheet({ userId, onOpenChange }: { userId: number | null; onOpenChange: (open: boolean) => void }) {
  const can = useAuthStore((s) => s.can)
  const qc = useQueryClient()
  const [banning, setBanning] = useState(false)
  const [blockPhone, setBlockPhone] = useState(true)
  const [hideAds, setHideAds] = useState(true)

  const { data: user, isLoading } = useQuery({
    queryKey: ['user', userId],
    queryFn: async () => (await api.get<UserDetail>(`/users/${userId}`)).data,
    enabled: userId !== null,
  })

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['users'] })
    qc.invalidateQueries({ queryKey: ['user'] })
    qc.invalidateQueries({ queryKey: ['ads'] })
    qc.invalidateQueries({ queryKey: ['blocked-phones'] })
  }
  const ban = useMutation({
    mutationFn: (reason: string) => api.post(`/users/${userId}/ban`, { reason, block_phone: blockPhone, hide_ads: hideAds }),
    onSuccess: () => {
      toast.success('User banned.')
      setBanning(false)
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const unverify = useMutation({
    mutationFn: () => api.post(`/users/${userId}/unverify`),
    onSuccess: () => {
      toast.success('Verified badge removed.')
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const unban = useMutation({
    mutationFn: () => api.post(`/users/${userId}/unban`),
    onSuccess: () => {
      toast.success('User can sign in again. Their old ads stay rejected.')
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <Sheet open={userId !== null} onOpenChange={onOpenChange}>
      <SheetContent className='flex w-full flex-col gap-0 p-0 sm:max-w-3xl'>
        <SheetHeader className='border-b'>
          <SheetTitle className='flex items-center gap-2 pe-6'>
            {user?.name ?? 'User'} {user?.banned && <Badge variant='destructive'>Banned</Badge>} {user?.verified && <Badge>Verified</Badge>} {user?.is_demo && <Badge variant='secondary'>Demo</Badge>}
          </SheetTitle>
          <SheetDescription>{user ? `#${user.id} · ${user.email}` : ''}</SheetDescription>
        </SheetHeader>
        <ScrollArea className='min-h-0 flex-1'>
          {isLoading || !user ? (
            <div className='space-y-3 p-4'>
              <Skeleton className='h-24' />
              <Skeleton className='h-48' />
            </div>
          ) : (
            <div className='space-y-5 p-4'>
              <dl className='grid grid-cols-2 gap-3 text-sm sm:grid-cols-4'>
                <Info label='Phone' value={user.phone ? `${user.phone}${user.whatsapp ? ' (WhatsApp)' : ''}` : '–'} />
                <Info label='City' value={user.city ?? '–'} />
                <Info label='Joined' value={format(new Date(user.created_at), 'd MMM yyyy')} />
                <Info label='Last sign-in' value={user.last_login_at ? format(new Date(user.last_login_at), 'd MMM yyyy') : '–'} />
                {Object.entries(user.ads_by_status).map(([status, total]) => (
                  <Info key={status} label={`Ads ${status}`} value={String(total)} />
                ))}
                <Info label='Reports made' value={String(user.reports_made)} />
              </dl>
              {user.phone_blocked && <p className='rounded-md bg-destructive/10 p-3 text-sm'>This phone number is blocked.</p>}
              {user.banned && <p className='rounded-md bg-destructive/10 p-3 text-sm'>Ban reason: {user.ban_reason}</p>}
              <div>
                <p className='mb-2 font-medium'>Their ads</p>
                <AdsTable userId={user.id} />
              </div>
            </div>
          )}
        </ScrollArea>
        {user && (can('users.ban') || (user.verified && can('users.verify'))) && (
          <div className='flex flex-wrap gap-2 border-t p-3'>
            {user.verified && can('users.verify') && (
              <Button variant='outline' onClick={() => window.confirm(`Remove the Verified badge of ${user.name}?`) && unverify.mutate()} disabled={unverify.isPending}>
                <BadgeX /> Remove Verified badge
              </Button>
            )}
            {!can('users.ban') ? null : user.banned ? (
              <Button variant='outline' onClick={() => unban.mutate()} disabled={unban.isPending}>
                <RotateCcw /> Unban
              </Button>
            ) : (
              <Button variant='destructive' onClick={() => setBanning(true)}>
                <Ban /> Ban user
              </Button>
            )}
          </div>
        )}
        <ReasonDialog
          open={banning}
          onOpenChange={setBanning}
          title={`Ban ${user?.name ?? 'user'}?`}
          description='They are signed out and cannot sign in again.'
          confirmText='Ban'
          destructive
          presets={['Scam', 'Fake ads', 'Rude to buyers', 'Spam']}
          busy={ban.isPending}
          onConfirm={(reason) => ban.mutate(reason)}
        >
          <div className='space-y-2 text-sm'>
            <label className='flex items-center gap-2'>
              <Checkbox checked={hideAds} onCheckedChange={(v) => setHideAds(!!v)} /> Take down all their live and waiting ads
            </label>
            <label className='flex items-center gap-2'>
              <Checkbox checked={blockPhone} onCheckedChange={(v) => setBlockPhone(!!v)} /> Block their phone number too
            </label>
          </div>
        </ReasonDialog>
      </SheetContent>
    </Sheet>
  )
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className='text-muted-foreground'>{label}</dt>
      <dd className='font-medium'>{value}</dd>
    </div>
  )
}
