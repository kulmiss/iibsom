import { type ColumnDef } from '@tanstack/react-table'
import { formatDistanceToNow } from 'date-fns'
import { Badge } from '@/components/ui/badge'
import { DataTableColumnHeader } from '@/components/data-table'
import { Page } from '@/components/layout/page'
import { ServerDataTable } from '@/components/server-data-table'

type Activity = { id: number; action: string; summary: string; subject: string | null; by: string; at: string }

const when = (iso: string) => (
  <time dateTime={iso} title={new Date(iso).toLocaleString()}>
    {formatDistanceToNow(new Date(iso), { addSuffix: true })}
  </time>
)

const columns: ColumnDef<Activity>[] = [
  { accessorKey: 'at', header: 'When', cell: ({ row }) => when(row.original.at), meta: { className: 'w-36 whitespace-nowrap' } },
  { accessorKey: 'by', header: 'Who', meta: { className: 'w-40' } },
  { accessorKey: 'action', header: ({ column }) => <DataTableColumnHeader column={column} title='What' />, cell: ({ row }) => <Badge variant='outline'>{row.original.action}</Badge>, enableSorting: false },
  { accessorKey: 'summary', header: 'Details' },
  { accessorKey: 'subject', header: 'Item', cell: ({ row }) => row.original.subject ?? '–' },
]

export function ActivityLog() {
  return (
    <Page title='Activity log' help='activity' description='Everything staff did in the admin: sign-ins, approvals, bans, changed switches and settings.'>
      <ServerDataTable<Activity>
        url='/activity'
        queryKey='activity'
        columns={columns}
        searchPlaceholder='Search details…'
        csvName='activity-log'
        rowId={(r) => String(r.id)}
        mobileCard={(a) => (
          <div className='space-y-1 text-sm'>
            <div className='flex items-center justify-between gap-2'>
              <Badge variant='outline'>{a.action}</Badge>
              <span className='text-xs text-muted-foreground'>{when(a.at)}</span>
            </div>
            <p>{a.summary}</p>
            <p className='text-xs text-muted-foreground'>by {a.by}</p>
          </div>
        )}
      />
    </Page>
  )
}
