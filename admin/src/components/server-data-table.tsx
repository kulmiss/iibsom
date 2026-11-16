import { useEffect, useMemo, useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  type ColumnDef,
  type ColumnFiltersState,
  type RowSelectionState,
  type SortingState,
  type Table as TableType,
  type VisibilityState,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table'
import { Download } from 'lucide-react'
import { api } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { DataTablePagination, DataTableToolbar } from '@/components/data-table'

export type ServerPage<T> = { data: T[]; meta: { page: number; last_page: number; total: number } }

type Filter = { columnId: string; title: string; options: { label: string; value: string }[] }

type Props<T> = {
  /** API path, e.g. "/activity". Query: search, page, per_page, sort, dir, <filter>=a,b */
  url: string
  queryKey: string
  columns: ColumnDef<T>[]
  filters?: Filter[]
  searchPlaceholder?: string
  /** Bulk actions bar for selected rows. */
  bulkActions?: (table: TableType<T>, clear: () => void) => React.ReactNode
  /** Phone card for one row (tables are hard to read on phones). */
  mobileCard?: (row: T) => React.ReactNode
  csvName?: string
  rowId?: (row: T) => string
}

/** Admin data table: server search/sort/filter/pagination, column show/hide, bulk select, CSV, phone cards. */
export function ServerDataTable<T>({ url, queryKey, columns, filters = [], searchPlaceholder = 'Search…', bulkActions, mobileCard, csvName, rowId }: Props<T>) {
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 20 })
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [globalFilter, setGlobalFilter] = useState('')
  const [search, setSearch] = useState('')
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({})
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({})

  // Wait until typing stops before searching.
  useEffect(() => {
    const t = setTimeout(() => {
      setSearch(globalFilter)
      setPagination((p) => ({ ...p, pageIndex: 0 }))
    }, 300)
    return () => clearTimeout(t)
  }, [globalFilter])

  const params = useMemo(() => {
    const p: Record<string, string | number> = { page: pagination.pageIndex + 1, per_page: pagination.pageSize }
    if (search) p.search = search
    if (sorting[0]) {
      p.sort = sorting[0].id
      p.dir = sorting[0].desc ? 'desc' : 'asc'
    }
    for (const f of columnFilters) {
      const v = f.value as string[] | string
      p[f.id] = Array.isArray(v) ? v.join(',') : v
    }
    return p
  }, [pagination, search, sorting, columnFilters])

  const query = useQuery({
    queryKey: [queryKey, url, params],
    queryFn: async () => (await api.get<ServerPage<T>>(url, { params })).data,
    placeholderData: keepPreviousData,
  })

  // eslint-disable-next-line react-hooks/incompatible-library
  const table = useReactTable({
    data: query.data?.data ?? [],
    columns,
    pageCount: query.data?.meta.last_page ?? 1,
    state: { pagination, sorting, columnFilters, globalFilter, columnVisibility, rowSelection },
    getRowId: rowId,
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
    enableRowSelection: !!bulkActions,
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onColumnFiltersChange: (u) => {
      setColumnFilters(u)
      setPagination((p) => ({ ...p, pageIndex: 0 }))
    },
    onGlobalFilterChange: setGlobalFilter,
    onColumnVisibilityChange: setColumnVisibility,
    onRowSelectionChange: setRowSelection,
    getCoreRowModel: getCoreRowModel(),
  })

  const rows = table.getRowModel().rows

  return (
    <div className='flex flex-1 flex-col gap-4'>
      <div className='flex flex-wrap items-center gap-2'>
        <div className='min-w-0 flex-1'>
          <DataTableToolbar table={table} searchPlaceholder={searchPlaceholder} filters={filters} />
        </div>
        {csvName && (
          <Button variant='outline' size='sm' className='h-8' onClick={() => downloadCsv(csvName, table)} disabled={!rows.length}>
            <Download /> CSV
          </Button>
        )}
      </div>

      {/* Phones: cards */}
      {mobileCard && (
        <ul className='grid gap-2 md:hidden'>
          {query.isLoading && [1, 2, 3].map((i) => <Skeleton key={i} className='h-20' />)}
          {rows.map((row) => (
            <li key={row.id} className='rounded-lg border bg-card p-3'>
              {mobileCard(row.original)}
            </li>
          ))}
          {!query.isLoading && rows.length === 0 && <li className='p-6 text-center text-muted-foreground'>Nothing here.</li>}
        </ul>
      )}

      {/* Bigger screens: table */}
      <div className={cn('overflow-hidden rounded-md border', mobileCard && 'max-md:hidden')}>
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((hg) => (
              <TableRow key={hg.id}>
                {hg.headers.map((h) => (
                  <TableHead key={h.id} colSpan={h.colSpan} className={cn(h.column.columnDef.meta?.className)}>
                    {h.isPlaceholder ? null : flexRender(h.column.columnDef.header, h.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {query.isLoading &&
              [1, 2, 3, 4, 5].map((i) => (
                <TableRow key={i}>
                  <TableCell colSpan={columns.length}>
                    <Skeleton className='h-6' />
                  </TableCell>
                </TableRow>
              ))}
            {rows.map((row) => (
              <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'} className={cn(query.isFetching && 'opacity-70')}>
                {row.getVisibleCells().map((cell) => (
                  <TableCell key={cell.id} className={cn(cell.column.columnDef.meta?.className)}>
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </TableCell>
                ))}
              </TableRow>
            ))}
            {!query.isLoading && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={columns.length} className='h-24 text-center text-muted-foreground'>
                  Nothing here.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      <DataTablePagination table={table} className='mt-auto' />
      {bulkActions && bulkActions(table, () => setRowSelection({}))}
      <p className='text-xs text-muted-foreground'>{query.data ? `${new Intl.NumberFormat('en').format(query.data.meta.total)} in total` : ''}</p>
    </div>
  )
}

/** Download the rows on this page as CSV (visible columns only). */
function downloadCsv<T>(name: string, table: TableType<T>) {
  const cols = table.getVisibleLeafColumns().filter((c) => c.id !== 'select' && c.id !== 'actions')
  const esc = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const header = cols.map((c) => esc(typeof c.columnDef.header === 'string' ? c.columnDef.header : c.id)).join(',')
  const lines = table.getRowModel().rows.map((r) => cols.map((c) => esc(r.getValue(c.id))).join(','))
  const blob = new Blob(['﻿' + [header, ...lines].join('\n')], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `${name}-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(a.href)
}
