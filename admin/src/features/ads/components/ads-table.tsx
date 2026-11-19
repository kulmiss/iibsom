import { useState } from 'react'
import { type ColumnDef, type Table } from '@tanstack/react-table'
import { formatDistanceToNow } from 'date-fns'
import { Check, ImageOff, Trash2, X } from 'lucide-react'
import { useAuthStore } from '@/stores/auth-store'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { DataTableBulkActions, DataTableColumnHeader } from '@/components/data-table'
import { ReasonDialog } from '@/components/reason-dialog'
import { ServerDataTable } from '@/components/server-data-table'
import { STATUS_OPTIONS, type AdRow } from '../types'
import { REJECT_PRESETS, useAdActions } from '../use-ad-actions'
import { AdSheet } from './ad-sheet'
import { EditedBadge, StatusBadge } from './status-badge'

function Thumb({ ad, big }: { ad: AdRow; big?: boolean }) {
  const size = big ? 'size-20' : 'size-12'
  return ad.thumb ? (
    <img src={ad.thumb} alt='' loading='lazy' className={`${size} shrink-0 rounded-md object-cover`} />
  ) : (
    <span className={`${size} flex shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground`}>
      <ImageOff className='size-4' />
    </span>
  )
}

const ago = (iso: string) => formatDistanceToNow(new Date(iso), { addSuffix: true })

/** All ads, or one status only (the approval queue). */
export function AdsTable({ status, userId, queue }: { status?: string; userId?: number; queue?: boolean }) {
  const can = useAuthStore((s) => s.can)
  const actions = useAdActions()
  const [openId, setOpenId] = useState<number | null>(null)
  const [rejectIds, setRejectIds] = useState<number[] | null>(null)
  const [deleteIds, setDeleteIds] = useState<number[] | null>(null)
  const [clearSelection, setClearSelection] = useState<() => void>(() => () => {})

  const quick = (ad: AdRow) =>
    can('ads.moderate') && ad.status === 'pending' ? (
      <div className='flex justify-end gap-1'>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button size='icon' variant='outline' className='size-8 text-emerald-700' aria-label='Approve' onClick={() => actions.approve.mutate(ad.id)}>
              <Check />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Approve</TooltipContent>
        </Tooltip>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button size='icon' variant='outline' className='size-8 text-destructive' aria-label='Reject' onClick={() => setRejectIds([ad.id])}>
              <X />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Reject</TooltipContent>
        </Tooltip>
      </div>
    ) : null

  const columns: ColumnDef<AdRow>[] = [
    ...(can('ads.moderate') || can('ads.delete')
      ? [
          {
            id: 'select',
            header: ({ table }) => (
              <Checkbox
                checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate')}
                onCheckedChange={(v) => table.toggleAllPageRowsSelected(!!v)}
                aria-label='Select all on this page'
              />
            ),
            cell: ({ row }) => <Checkbox checked={row.getIsSelected()} onCheckedChange={(v) => row.toggleSelected(!!v)} aria-label='Select ad' />,
            enableSorting: false,
            enableHiding: false,
            meta: { className: 'w-8' },
          } satisfies ColumnDef<AdRow>,
        ]
      : []),
    {
      accessorKey: 'title',
      header: ({ column }) => <DataTableColumnHeader column={column} title='Ad' />,
      cell: ({ row }) => (
        <div className='flex min-w-64 items-center gap-3'>
          <Thumb ad={row.original} big={queue} />
          <div className='min-w-0'>
            <p className='truncate font-medium'>{row.original.title}</p>
            <p className='text-xs text-muted-foreground'>
              #{row.original.id} · {row.original.category} · {row.original.photos_count ?? 0} photos
              {row.original.is_demo && <Badge variant='secondary' className='ms-1'>Demo</Badge>}
              {row.original.edited && <EditedBadge />}
            </p>
            {row.original.rejection_reason && <p className='truncate text-xs text-destructive'>{row.original.rejection_reason}</p>}
          </div>
        </div>
      ),
    },
    { accessorKey: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.status} label={row.original.status_label} />, enableSorting: false },
    {
      accessorKey: 'flag',
      header: 'Check',
      enableSorting: false,
      cell: ({ row }) => (row.original.flag ? <Badge variant='destructive' title={row.original.flag}>Drugs</Badge> : null),
    },
    { accessorKey: 'price', header: ({ column }) => <DataTableColumnHeader column={column} title='Price' />, meta: { className: 'whitespace-nowrap' } },
    { accessorKey: 'city', header: 'City', enableSorting: false },
    {
      accessorKey: 'seller',
      header: 'Seller',
      enableSorting: false,
      cell: ({ row }) => (
        <span>
          {row.original.seller} {row.original.seller_banned && <Badge variant='destructive'>Banned</Badge>}
        </span>
      ),
    },
    { accessorKey: 'created_at', header: ({ column }) => <DataTableColumnHeader column={column} title='Posted' />, cell: ({ row }) => <span className='whitespace-nowrap'>{ago(row.original.created_at)}</span> },
    { id: 'actions', header: '', cell: ({ row }) => quick(row.original), enableHiding: false },
  ]

  const ids = (table: Table<AdRow>) => table.getSelectedRowModel().rows.map((r) => r.original.id)

  return (
    <>
      <ServerDataTable<AdRow>
        url='/ads'
        queryKey='ads'
        columns={columns}
        extraParams={{ ...(status ? { status } : {}), ...(userId ? { user_id: userId } : {}) }}
        filters={[
          ...(status ? [] : [{ columnId: 'status', title: 'Status', options: STATUS_OPTIONS }]),
          // Ads sent back by the drug check, or reported as drugs (PR 72).
          { columnId: 'flag', title: 'Drugs', options: [{ value: 'drugs', label: 'Drug check or drug reports' }] },
        ]}
        searchPlaceholder='Search title or #id…'
        csvName={queue ? 'ads-waiting' : 'ads'}
        rowId={(r) => String(r.id)}
        onRowClick={(r) => setOpenId(r.id)}
        mobileCard={(ad) => (
          <div className='flex gap-3'>
            <Thumb ad={ad} big />
            <div className='min-w-0 flex-1 space-y-1'>
              <p className='line-clamp-2 font-medium'>{ad.title}</p>
              <p className='text-sm font-semibold'>{ad.price}</p>
              <div className='flex flex-wrap items-center gap-1 text-xs text-muted-foreground'>
                <StatusBadge status={ad.status} label={ad.status_label} /> {ad.edited && <EditedBadge />} {ad.city} · {ago(ad.created_at)}
              </div>
            </div>
          </div>
        )}
        bulkActions={(table, clear) => (
          <DataTableBulkActions table={table} entityName='ad'>
            {can('ads.moderate') && (
              <>
                <Button size='sm' onClick={() => actions.bulk.mutate({ ids: ids(table), action: 'approve' }, { onSuccess: clear })}>
                  <Check /> Approve
                </Button>
                <Button
                  size='sm'
                  variant='outline'
                  onClick={() => {
                    setClearSelection(() => clear)
                    setRejectIds(ids(table))
                  }}
                >
                  <X /> Reject
                </Button>
              </>
            )}
            {can('ads.delete') && (
              <Button
                size='sm'
                variant='destructive'
                onClick={() => {
                  setClearSelection(() => clear)
                  setDeleteIds(ids(table))
                }}
              >
                <Trash2 /> Delete
              </Button>
            )}
          </DataTableBulkActions>
        )}
      />

      <AdSheet adId={openId} onOpenChange={(o) => !o && setOpenId(null)} />

      <ReasonDialog
        open={rejectIds !== null}
        onOpenChange={(o) => !o && setRejectIds(null)}
        title={rejectIds && rejectIds.length > 1 ? `Reject ${rejectIds.length} ads?` : 'Reject this ad?'}
        description='The seller will see the reason and can fix the ad.'
        confirmText='Reject'
        destructive
        presets={REJECT_PRESETS}
        busy={actions.bulk.isPending || actions.reject.isPending}
        onConfirm={(reason) => {
          if (!rejectIds) return
          const done = () => {
            setRejectIds(null)
            clearSelection()
          }
          if (rejectIds.length === 1) actions.reject.mutate({ id: rejectIds[0]!, reason }, { onSuccess: done })
          else actions.bulk.mutate({ ids: rejectIds, action: 'reject', reason }, { onSuccess: done })
        }}
      />
      <ConfirmDialog
        open={deleteIds !== null}
        onOpenChange={(o) => !o && setDeleteIds(null)}
        title={`Delete ${deleteIds?.length ?? 0} ads?`}
        desc='They disappear from the site. The data is kept for records.'
        confirmText='Delete'
        destructive
        handleConfirm={() =>
          deleteIds &&
          actions.bulk.mutate(
            { ids: deleteIds, action: 'delete' },
            {
              onSuccess: () => {
                setDeleteIds(null)
                clearSelection()
              },
            }
          )
        }
      />
    </>
  )
}
