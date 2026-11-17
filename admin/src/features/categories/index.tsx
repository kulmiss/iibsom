import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronRight, ListPlus, Pencil, Plus } from 'lucide-react'
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

  const q = search.toLowerCase()
  const mains = (data?.categories ?? []).filter((m) => !q || m.name_en.toLowerCase().includes(q) || m.children?.some((c) => c.name_en.toLowerCase().includes(q)))

  const line = (c: CategoryRow, sub: boolean) => (
    <div className={cn('flex items-center gap-3 px-3 py-2', sub && 'ps-10', !c.enabled && 'opacity-60')}>
      <span className='flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary'>
        <CategoryIcon name={c.icon} />
      </span>
      <div className='min-w-0 flex-1'>
        <p className='truncate font-medium'>
          {c.name_en} {c.popular && <Badge variant='secondary'>Popular</Badge>}
        </p>
        <p className='text-xs text-muted-foreground'>
          {c.name_so ?? 'No Somali name yet'} · {c.ads_count} live ads{sub ? ` · ${c.fields_count} fields` : ''}
        </p>
      </div>
      {sub && (
        <Button size='sm' variant='ghost' onClick={() => setFieldsFor(c)}>
          <ListPlus /> Fields
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
      description='Add, edit and switch categories on or off. No code needed.'
      actions={
        <Button onClick={() => setDialog({ category: null })}>
          <Plus /> Add category
        </Button>
      }
    >
      <Input placeholder='Find a category…' value={search} onChange={(e) => setSearch(e.target.value)} className='h-9 max-w-xs' />
      {isLoading && <Skeleton className='h-96' />}
      <div className='divide-y rounded-lg border'>
        {mains.map((m) => (
          <Collapsible key={m.id} defaultOpen={!!q}>
            <div className='flex items-center'>
              <CollapsibleTrigger asChild>
                <Button variant='ghost' size='icon' className='ms-1 size-8 [&[data-state=open]>svg]:rotate-90' aria-label='Show sub categories'>
                  <ChevronRight className='transition' />
                </Button>
              </CollapsibleTrigger>
              <div className='flex-1'>{line(m, false)}</div>
            </div>
            <CollapsibleContent className='border-t bg-muted/30'>
              {m.children?.map((c) => (
                <div key={c.id} className='border-b last:border-0'>
                  {line(c, true)}
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
