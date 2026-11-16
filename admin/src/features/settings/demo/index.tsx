import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { ContentSection } from '../components/content-section'

type Demo = { show: boolean; counts: { ads: number; users: number; reports: number } }

export function SettingsDemo() {
  const qc = useQueryClient()
  const [confirm, setConfirm] = useState('')
  const { data } = useQuery({ queryKey: ['demo'], queryFn: async () => (await api.get<Demo>('/demo')).data })
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['demo'] })
    qc.invalidateQueries({ queryKey: ['dashboard'] })
  }
  const toggle = useMutation({
    mutationFn: (on: boolean) => api.put('/features/site.demo_data', { enabled: on }),
    onSuccess: (_, on) => {
      toast.success(on ? 'Demo data is shown.' : 'Demo data is hidden everywhere.')
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const del = useMutation({
    mutationFn: () => api.post<{ deleted: Demo['counts'] }>('/demo/delete', { confirm }).then((r) => r.data),
    onSuccess: (r) => {
      toast.success(`Deleted ${r.deleted.ads} ads, ${r.deleted.users} users, ${r.deleted.reports} reports.`)
      setConfirm('')
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <ContentSection title='Demo data' desc='Practice data that makes the site look busy. Always marked as demo and never counted in real numbers.'>
      <div className='space-y-6'>
        <label className='flex items-center justify-between gap-4 rounded-lg border p-4'>
          <span>
            <span className='block font-medium'>Show demo data</span>
            <span className='text-sm text-muted-foreground'>OFF = hidden everywhere: site, search, admin lists and charts.</span>
          </span>
          <Switch checked={!!data?.show} onCheckedChange={(v) => toggle.mutate(v)} />
        </label>
        <div className='space-y-3 rounded-lg border border-destructive/40 p-4'>
          <p className='font-medium'>Delete all demo data (before going live)</p>
          <p className='text-sm text-muted-foreground'>
            {data ? `${data.counts.ads} ads, ${data.counts.users} users, ${data.counts.reports} reports.` : '…'} Real data is not touched. This cannot be undone.
          </p>
          <div className='grid max-w-xs gap-2'>
            <Label htmlFor='demo-confirm'>Type DELETE to confirm</Label>
            <Input id='demo-confirm' value={confirm} onChange={(e) => setConfirm(e.target.value)} autoComplete='off' />
          </div>
          <Button variant='destructive' disabled={confirm !== 'DELETE' || del.isPending} onClick={() => del.mutate()}>
            Delete all demo data
          </Button>
        </div>
      </div>
    </ContentSection>
  )
}
