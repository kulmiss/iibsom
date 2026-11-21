import { useState } from 'react'
import { useNavigate, useSearch } from '@tanstack/react-router'
import { type ColumnDef } from '@tanstack/react-table'
import { formatDistanceToNow } from 'date-fns'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { DataTableColumnHeader } from '@/components/data-table'
import { Page } from '@/components/layout/page'
import { ServerDataTable } from '@/components/server-data-table'
import { BlockedPhones } from './components/blocked-phones'
import { UserSheet } from './components/user-sheet'
import { type UserRow } from './types'

const STATE = (u: UserRow) => (
  <>
    {u.banned ? <Badge variant='destructive'>Banned</Badge> : u.profile_complete ? <Badge variant='outline'>Active</Badge> : <Badge variant='secondary'>Profile not finished</Badge>}{' '}
    {u.flag && <Badge variant='destructive' title={u.flag}>Drugs</Badge>}
  </>
)

const columns: ColumnDef<UserRow>[] = [
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title='Name' />,
    cell: ({ row }) => (
      <div>
        <p className='font-medium'>
          {row.original.name} {row.original.is_demo && <Badge variant='secondary'>Demo</Badge>}
        </p>
        <p className='text-xs text-muted-foreground'>{row.original.email}</p>
      </div>
    ),
  },
  { accessorKey: 'phone', header: 'Phone', cell: ({ row }) => <span className='whitespace-nowrap'>{row.original.phone ?? '–'}</span> },
  { accessorKey: 'city', header: 'City', cell: ({ row }) => row.original.city ?? '–' },
  { accessorKey: 'ads_count', header: ({ column }) => <DataTableColumnHeader column={column} title='Ads' /> },
  { id: 'state', header: 'State', cell: ({ row }) => STATE(row.original) },
  { accessorKey: 'created_at', header: ({ column }) => <DataTableColumnHeader column={column} title='Joined' />, cell: ({ row }) => formatDistanceToNow(new Date(row.original.created_at), { addSuffix: true }) },
  { accessorKey: 'last_login_at', header: ({ column }) => <DataTableColumnHeader column={column} title='Last sign-in' />, cell: ({ row }) => (row.original.last_login_at ? formatDistanceToNow(new Date(row.original.last_login_at), { addSuffix: true }) : '–') },
]

export function Users() {
  const search = useSearch({ from: '/_authenticated/users' })
  const navigate = useNavigate({ from: '/users' })
  const [openId, setOpenId] = useState<number | null>(search.open ?? null)

  return (
    <Page title='Users' help='users' description='Customers (Google or email sign-in). Tap a user to see their ads, confirm their phone or ban them.'>
      <Tabs defaultValue='users' className='flex flex-1 flex-col'>
        <TabsList>
          <TabsTrigger value='users'>Users</TabsTrigger>
          <TabsTrigger value='phones'>Blocked phone numbers</TabsTrigger>
        </TabsList>
        <TabsContent value='users' className='flex flex-1 flex-col'>
          <ServerDataTable<UserRow>
            url='/users'
            queryKey='users'
            columns={columns}
            filters={[{ columnId: 'state', title: 'State', options: [{ value: 'active', label: 'Active' }, { value: 'incomplete', label: 'Profile not finished' }, { value: 'banned', label: 'Banned' }, { value: 'drugs', label: 'Drugs (chat)' }] }]}
            searchPlaceholder='Name, email, phone, #id or code…'
            csvName='users'
            rowId={(r) => String(r.id)}
            onRowClick={(u) => setOpenId(u.id)}
            mobileCard={(u) => (
              <div className='space-y-1 text-sm'>
                <div className='flex items-center justify-between gap-2'>
                  <p className='font-medium'>{u.name}</p>
                  {STATE(u)}
                </div>
                <p className='text-muted-foreground'>
                  {u.phone ?? 'No phone'} · {u.city ?? '–'} · {u.ads_count ?? 0} ads
                </p>
              </div>
            )}
          />
        </TabsContent>
        <TabsContent value='phones' className='flex flex-1 flex-col'>
          <BlockedPhones />
        </TabsContent>
      </Tabs>
      <UserSheet
        userId={openId}
        onOpenChange={(o) => {
          if (!o) {
            setOpenId(null)
            navigate({ search: {} })
          }
        }}
      />
    </Page>
  )
}
