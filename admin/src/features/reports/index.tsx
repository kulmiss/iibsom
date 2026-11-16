import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { type ColumnDef } from '@tanstack/react-table'
import { formatDistanceToNow } from 'date-fns'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Page } from '@/components/layout/page'
import { ReasonDialog } from '@/components/reason-dialog'
import { ServerDataTable } from '@/components/server-data-table'
import { AdSheet } from '@/features/ads/components/ad-sheet'

type ReportRow = {
  id: number
  reason: string
  reason_label: string
  details: string | null
  status: 'waiting' | 'handled' | 'rejected'
  ad_id: number
  ad_title: string | null
  ad_status: string | null
  seller: string | null
  reporter: string | null
  handled_by: string | null
  staff_note: string | null
  created_at: string
}

const STATUS = { waiting: 'Waiting', handled: 'Ad removed', rejected: 'Dismissed' } as const

export function Reports() {
  const can = useAuthStore((s) => s.can)
  const qc = useQueryClient()
  const [openAd, setOpenAd] = useState<number | null>(null)
  const [acting, setActing] = useState<{ report: ReportRow; action: 'dismiss' | 'remove_ad' } | null>(null)

  const handle = useMutation({
    mutationFn: ({ id, action, note }: { id: number; action: string; note: string }) => api.post(`/reports/${id}/handle`, { action, note }),
    onSuccess: (_, v) => {
      toast.success(v.action === 'dismiss' ? 'Report dismissed.' : 'Ad taken down. All reports on it are closed.')
      setActing(null)
      qc.invalidateQueries({ queryKey: ['reports'] })
      qc.invalidateQueries({ queryKey: ['ads'] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const columns: ColumnDef<ReportRow>[] = [
    {
      accessorKey: 'ad_title',
      header: 'Ad',
      cell: ({ row }) => (
        <button type='button' className='text-start' onClick={() => setOpenAd(row.original.ad_id)}>
          <p className='font-medium underline-offset-4 hover:underline'>{row.original.ad_title ?? `Ad #${row.original.ad_id}`}</p>
          <p className='text-xs text-muted-foreground'>
            #{row.original.ad_id} · seller {row.original.seller} · ad {row.original.ad_status}
          </p>
        </button>
      ),
    },
    {
      accessorKey: 'reason',
      header: 'Reason',
      cell: ({ row }) => (
        <div className='max-w-64'>
          <Badge variant={row.original.reason === 'scam' ? 'destructive' : 'outline'}>{row.original.reason_label}</Badge>
          {row.original.details && <p className='mt-1 line-clamp-2 text-xs text-muted-foreground'>{row.original.details}</p>}
        </div>
      ),
    },
    { accessorKey: 'reporter', header: 'Reported by' },
    { accessorKey: 'status', header: 'Status', cell: ({ row }) => <Badge variant={row.original.status === 'waiting' ? 'default' : 'secondary'}>{STATUS[row.original.status]}</Badge> },
    { accessorKey: 'created_at', header: 'When', cell: ({ row }) => formatDistanceToNow(new Date(row.original.created_at), { addSuffix: true }) },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) =>
        can('reports.handle') &&
        row.original.status === 'waiting' && (
          <div className='flex justify-end gap-1'>
            <Button size='sm' variant='destructive' onClick={() => setActing({ report: row.original, action: 'remove_ad' })}>
              Take ad down
            </Button>
            <Button size='sm' variant='outline' onClick={() => setActing({ report: row.original, action: 'dismiss' })}>
              Dismiss
            </Button>
          </div>
        ),
    },
  ]

  return (
    <Page title='Reports' description='Ads people reported. Waiting ones come first.'>
      <ServerDataTable<ReportRow>
        url='/reports'
        queryKey='reports'
        columns={columns}
        filters={[
          { columnId: 'status', title: 'Status', options: [{ value: 'waiting', label: 'Waiting' }, { value: 'handled', label: 'Ad removed' }, { value: 'rejected', label: 'Dismissed' }] },
          {
            columnId: 'reason',
            title: 'Reason',
            options: [
              { value: 'scam', label: 'Scam or fraud' },
              { value: 'wrong_category', label: 'Wrong category' },
              { value: 'sold', label: 'Already sold' },
              { value: 'offensive', label: 'Offensive' },
              { value: 'other', label: 'Something else' },
            ],
          },
        ]}
        searchPlaceholder='Ad title or #id…'
        csvName='reports'
        rowId={(r) => String(r.id)}
        mobileCard={(r) => (
          <div className='space-y-2 text-sm'>
            <div className='flex items-center justify-between gap-2'>
              <Badge variant={r.reason === 'scam' ? 'destructive' : 'outline'}>{r.reason_label}</Badge>
              <Badge variant={r.status === 'waiting' ? 'default' : 'secondary'}>{STATUS[r.status]}</Badge>
            </div>
            <button type='button' className='text-start font-medium underline' onClick={() => setOpenAd(r.ad_id)}>
              {r.ad_title}
            </button>
            {r.details && <p className='text-muted-foreground'>{r.details}</p>}
            {can('reports.handle') && r.status === 'waiting' && (
              <div className='flex gap-2'>
                <Button size='sm' variant='destructive' onClick={() => setActing({ report: r, action: 'remove_ad' })}>
                  Take ad down
                </Button>
                <Button size='sm' variant='outline' onClick={() => setActing({ report: r, action: 'dismiss' })}>
                  Dismiss
                </Button>
              </div>
            )}
          </div>
        )}
      />
      <AdSheet adId={openAd} onOpenChange={(o) => !o && setOpenAd(null)} />
      <ReasonDialog
        open={acting !== null}
        onOpenChange={(o) => !o && setActing(null)}
        title={acting?.action === 'remove_ad' ? 'Take this ad down?' : 'Dismiss this report?'}
        description={acting?.action === 'remove_ad' ? 'The ad is rejected and every waiting report on it is closed. The seller sees your note (or the report reason).' : 'The ad stays as it is.'}
        label='Note'
        required={false}
        destructive={acting?.action === 'remove_ad'}
        confirmText={acting?.action === 'remove_ad' ? 'Take down' : 'Dismiss'}
        busy={handle.isPending}
        onConfirm={(note) => acting && handle.mutate({ id: acting.report.id, action: acting.action, note })}
      />
    </Page>
  )
}
