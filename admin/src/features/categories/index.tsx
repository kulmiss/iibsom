import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowDown, ArrowUp, ChevronRight, GripVertical, ListPlus, Pencil, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { CategoryIcon } from '@/components/category-icon'
import { Page } from '@/components/layout/page'
import { CategoryDialog } from './category-dialog'
import { FieldsSheet } from './fields-sheet'
import { type CategoryRow } from './types'

export function Categories() {
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [dialog, setDialog] = useState<{ category: CategoryRow | null; parentId?: number | null } | null>(null)
  const [fieldsFor, setFieldsFor] = useState<CategoryRow | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['categories'],
    queryFn: async () => (await api.get<{ categories: CategoryRow[]; icons: string[] }>('/categories')).data,
  })

  const toggle = useMutation({
    mutationFn: (c: CategoryRow) => api.put(`/categories/${c.id}`, { ...c, enabled: !c.enabled, name_so: c.name_so ?? '' }),
    onSuccess: (_, c) => {
      toast.success(`${c.name_en} is ${c.enabled ? 'OFF' : 'ON'} now.`)
      qc.invalidateQueries({ queryKey: ['categories'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  // Drag-and-drop (mouse) or the arrow buttons (keyboard, phones): one list at a time.
  const reorder = useMutation({
    mutationFn: (ids: number[]) => api.post('/categories/reorder', { ids }),
    onSuccess: () => toast.success('New order saved.'),
    onError: (e) => {
      toast.error(apiErrorMessage(e))
      qc.invalidateQueries({ queryKey: ['categories'] })
    },
  })
  const [dragging, setDragging] = useState<{ id: number; parentId: number | null } | null>(null)
  const move = (list: CategoryRow[], from: number, to: number) => {
    if (to < 0 || to >= list.length || from === to) return
    const next = [...list]
    const [item] = next.splice(from, 1)
    next.splice(to, 0, item)
    const parentId = item.parent_id
    qc.setQueryData<{ categories: CategoryRow[]; icons: string[] }>(['categories'], (old) =>
      old && {
        ...old,
        categories: parentId === null ? next : old.categories.map((m) => (m.id === parentId ? { ...m, children: next } : m)),
      }
    )
    reorder.mutate(next.map((c) => c.id))
  }
  const handle = (list: CategoryRow[], index: number) => {
    const c = list[index]
    return (
      <div
        className='flex shrink-0 items-center'
        draggable={!search}
        onDragStart={(e) => {
          setDragging({ id: c.id, parentId: c.parent_id })
          e.dataTransfer.effectAllowed = 'move'
        }}
        onDragEnd={() => setDragging(null)}
      >
        {!search && <GripVertical className='hidden size-4 cursor-grab text-muted-foreground sm:block' aria-hidden='true' />}
        <Button size='icon' variant='ghost' className='size-7' disabled={!!search || index === 0 || reorder.isPending} aria-label={`Move ${c.name_en} up`} onClick={() => move(list, index, index - 1)}>
          <ArrowUp />
        </Button>
        <Button size='icon' variant='ghost' className='size-7' disabled={!!search || index === list.length - 1 || reorder.isPending} aria-label={`Move ${c.name_en} down`} onClick={() => move(list, index, index + 1)}>
          <ArrowDown />
        </Button>
      </div>
    )
  }
  const dropProps = (list: CategoryRow[], index: number) => ({
    onDragOver: (e: React.DragEvent) => {
      if (dragging && dragging.parentId === list[index].parent_id) e.preventDefault() // only inside the same list
    },
    onDrop: (e: React.DragEvent) => {
      e.preventDefault()
      if (!dragging || dragging.parentId !== list[index].parent_id) return
      move(list, list.findIndex((c) => c.id === dragging.id), index)
      setDragging(null)
    },
  })

  const q = search.toLowerCase()
  const mains = (data?.categories ?? []).filter((m) => !q || m.name_en.toLowerCase().includes(q) || m.children?.some((c) => c.name_en.toLowerCase().includes(q)))

  const line = (c: CategoryRow, sub: boolean, list: CategoryRow[], index: number) => (
    <div className={cn('flex flex-wrap items-center gap-x-2 gap-y-1 px-2 py-2 sm:flex-nowrap sm:gap-3', sub && 'sm:ps-8', !c.enabled && 'opacity-60', dragging?.id === c.id && 'opacity-40')} {...dropProps(list, index)}>
      {handle(list, index)}
      <span className='flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary'>
        <CategoryIcon name={c.icon} />
      </span>
      <div className='min-w-[9rem] flex-1'>
        <p className='font-medium break-words sm:truncate'>
          {c.name_en} {c.popular && <Badge variant='secondary'>Popular</Badge>}
        </p>
        <p className='text-xs text-muted-foreground'>
          {c.name_so ?? 'No Somali name yet'} · {c.ads_count} live ads{sub ? ` · ${c.fields_count} ${c.fields_count === 1 ? 'field' : 'fields'}` : ''}
        </p>
      </div>
      {sub && (
        <Button size='sm' variant='ghost' onClick={() => setFieldsFor(c)} aria-label={`Fields of ${c.name_en}`}>
          <ListPlus /> <span className='hidden sm:inline'>Fields</span>
        </Button>
      )}
      <Button size='icon' variant='ghost' className='size-8' aria-label={`Edit ${c.name_en}`} onClick={() => setDialog({ category: c })}>
        <Pencil />
      </Button>
      <Switch checked={c.enabled} onCheckedChange={() => toggle.mutate(c)} aria-label={`${c.name_en} on or off`} />
    </div>
  )

  return (
    <Page
      title='Categories'
      help='categories'
      description='Add, edit and switch categories on or off. Drag the dots (or use the arrows) to change the order. No code needed.'
      actions={
        <Button onClick={() => setDialog({ category: null })}>
          <Plus /> Add category
        </Button>
      }
    >
      <Input placeholder='Find a category…' value={search} onChange={(e) => setSearch(e.target.value)} className='h-9 max-w-xs' />
      {isLoading && <Skeleton className='h-96' />}
      <div className='divide-y rounded-lg border'>
        {mains.map((m, mi) => (
          <Collapsible key={m.id} defaultOpen={!!q}>
            <div className='flex items-center'>
              <CollapsibleTrigger asChild>
                <Button variant='ghost' size='icon' className='ms-1 size-8 [&[data-state=open]>svg]:rotate-90' aria-label='Show sub categories'>
                  <ChevronRight className='transition' />
                </Button>
              </CollapsibleTrigger>
              <div className='min-w-0 flex-1'>{line(m, false, mains, mi)}</div>
            </div>
            <CollapsibleContent className='border-t bg-muted/30'>
              {m.children?.map((c, ci) => (
                <div key={c.id} className='border-b last:border-0'>
                  {line(c, true, m.children ?? [], ci)}
                </div>
              ))}
              <div className='ps-10 py-2'>
                <Button size='sm' variant='ghost' onClick={() => setDialog({ category: null, parentId: m.id })}>
                  <Plus /> Add sub category in {m.name_en}
                </Button>
              </div>
            </CollapsibleContent>
          </Collapsible>
        ))}
      </div>
      <CategoryDialog
        key={dialog ? `${dialog.category?.id ?? 'new'}-${dialog.parentId ?? ''}` : 'closed'}
        open={!!dialog}
        onOpenChange={(o) => !o && setDialog(null)}
        category={dialog?.category ?? null}
        defaultParentId={dialog?.parentId ?? null}
        mains={data?.categories ?? []}
        icons={data?.icons ?? []}
      />
      <FieldsSheet category={fieldsFor} onOpenChange={(o) => !o && setFieldsFor(null)} />
    </Page>
  )
}
