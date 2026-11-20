import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, Rocket } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'

type Change = { key: string; label: string; from: boolean; to: boolean; why: string }
type Preview = { changes: Change[]; total: number }

const onOff = (on: boolean) => <Badge variant={on ? 'default' : 'secondary'}>{on ? 'ON' : 'OFF'}</Badge>

/**
 * "Launch setup" (PR 106): shows exactly which switches would change, and why; one click sets them.
 * Only the switches in the list change. The list is sent back, so nothing unseen can change.
 */
export function LaunchSetup() {
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const preview = useQuery({
    queryKey: ['features-launch'],
    queryFn: async () => (await api.get<Preview>('/features/launch')).data,
    enabled: open,
    staleTime: 0,
  })
  const apply = useMutation({
    mutationFn: (keys: string[]) => api.post<{ changed: number }>('/features/launch', { keys }),
    onSuccess: (res) => {
      toast.success(res.data.changed ? `Launch setup done: ${res.data.changed} switch${res.data.changed === 1 ? '' : 'es'} changed.` : 'Already set for launch. Nothing changed.')
      setOpen(false)
      qc.invalidateQueries({ queryKey: ['features'] })
    },
    onError: (e) => {
      toast.error(apiErrorMessage(e))
      preview.refetch()
    },
  })
  const changes = preview.data?.changes ?? []

  return (
    <>
      <Button size='sm' onClick={() => setOpen(true)} data-launch-setup>
        <Rocket /> Launch setup
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className='max-h-[90dvh] overflow-y-auto sm:max-w-xl'>
          <DialogHeader>
            <DialogTitle>Launch setup</DialogTitle>
            <DialogDescription>
              The recommended switches for the first days of the live site. Only the {preview.data?.total ?? ''} switches in this setup can change; every other switch stays as you set it. You can change any of them again later.
            </DialogDescription>
          </DialogHeader>
          {preview.isLoading && <Skeleton className='h-40' />}
          {preview.isError && <p className='text-sm text-destructive'>{apiErrorMessage(preview.error)}</p>}
          {preview.data && changes.length === 0 && <p className='text-sm' data-launch-none>Everything is already set for launch. Nothing would change.</p>}
          {changes.length > 0 && (
            <ul className='divide-y rounded-md border' data-launch-list>
              {changes.map((c) => (
                <li key={c.key} className='grid gap-1 p-3' data-launch-item={c.key}>
                  <p className='flex flex-wrap items-center gap-2 font-medium'>
                    {c.label} <span className='flex items-center gap-1'>{onOff(c.from)}<ArrowRight className='size-3.5' aria-label='becomes' />{onOff(c.to)}</span>
                  </p>
                  <p className='text-sm text-muted-foreground'>{c.why}</p>
                </li>
              ))}
            </ul>
          )}
          <DialogFooter>
            <Button variant='outline' onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!preview.data || changes.length === 0 || apply.isPending} onClick={() => apply.mutate(changes.map((c) => c.key))} data-launch-apply>
              {apply.isPending ? 'Changing…' : `Change ${changes.length} switch${changes.length === 1 ? '' : 'es'}`}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
