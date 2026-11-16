import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { Page } from '@/components/layout/page'

type Feature = { key: string; label: string; description: string; enabled: boolean; default: boolean; changed: boolean; effect_off: string }
type Data = { summary: { on: number; off: number }; groups: { group: string; items: Feature[] }[] }

export function FeatureSwitches() {
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [pending, setPending] = useState<Feature | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['features'], queryFn: async () => (await api.get<Data>('/features')).data })

  const refresh = () => qc.invalidateQueries({ queryKey: ['features'] })
  const toggle = useMutation({
    mutationFn: (f: Feature) => api.put(`/features/${f.key}`, { enabled: !f.enabled }),
    onSuccess: (_, f) => {
      toast.success(`“${f.label}” is ${f.enabled ? 'OFF' : 'ON'} now.`)
      setPending(null)
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const reset = useMutation({ mutationFn: (f: Feature) => api.delete(`/features/${f.key}`), onSuccess: refresh })

  const q = search.toLowerCase()
  const groups = (data?.groups ?? [])
    .map((g) => ({ ...g, items: g.items.filter((f) => !q || `${f.label} ${f.description} ${g.group}`.toLowerCase().includes(q)) }))
    .filter((g) => g.items.length)

  return (
    <Page title='Feature switches' help='switches' description='One switch for every feature. OFF = hidden everywhere, as if it never existed. Data is kept.'>
      <div className='flex flex-wrap items-center gap-3'>
        <Input placeholder='Find a switch…' value={search} onChange={(e) => setSearch(e.target.value)} className='h-9 max-w-xs' />
        {data && (
          <p className='text-sm text-muted-foreground'>
            <Badge>{data.summary.on} ON</Badge> <Badge variant='secondary'>{data.summary.off} OFF</Badge>
          </p>
        )}
      </div>
      {isLoading && <Skeleton className='h-96' />}
      <div className='grid gap-4 lg:grid-cols-2'>
        {groups.map((g) => (
          <Card key={g.group}>
            <CardHeader>
              <CardTitle className='text-base'>{g.group}</CardTitle>
            </CardHeader>
            <CardContent className='divide-y'>
              {g.items.map((f) => (
                <div key={f.key} className='flex items-start gap-3 py-3 first:pt-0 last:pb-0'>
                  <div className='min-w-0 flex-1'>
                    <p className='font-medium'>
                      {f.label} {f.changed && <Badge variant='outline'>changed</Badge>}
                    </p>
                    <p className='text-sm text-muted-foreground'>{f.description}</p>
                  </div>
                  {f.changed && (
                    <Button size='icon' variant='ghost' className='size-8' title='Back to default' aria-label={`Reset ${f.label}`} onClick={() => reset.mutate(f)}>
                      <RotateCcw />
                    </Button>
                  )}
                  <Switch checked={f.enabled} onCheckedChange={() => setPending(f)} aria-label={`${f.label} on or off`} />
                </div>
              ))}
            </CardContent>
          </Card>
        ))}
      </div>
      <ConfirmDialog
        open={!!pending}
        onOpenChange={(o) => !o && setPending(null)}
        title={pending ? `Switch “${pending.label}” ${pending.enabled ? 'OFF' : 'ON'}?` : ''}
        desc={pending ? (pending.enabled ? `What changes: ${pending.effect_off}` : `What changes: ${pending.description} It shows up again everywhere.`) : ''}
        confirmText={pending?.enabled ? 'Switch OFF' : 'Switch ON'}
        destructive={pending?.enabled}
        isLoading={toggle.isPending}
        handleConfirm={() => pending && toggle.mutate(pending)}
      />
    </Page>
  )
}
