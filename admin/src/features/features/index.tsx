import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronDown, ChevronsDownUp, ChevronsUpDown, RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { Page } from '@/components/layout/page'

type Feature = { key: string; label: string; description: string; enabled: boolean; default: boolean; changed: boolean; effect_off: string }
type Data = { summary: { on: number; off: number; changed: number }; groups: { group: string; items: Feature[] }[] }
type Show = 'all' | 'on' | 'off' | 'changed'
type Sort = 'page' | 'az' | 'on' | 'off'

const STORE = 'iibsom.admin.switches.closed'

/** Which sections are closed, kept in this browser only (a convenience; never needed). */
function readClosed(): string[] {
  try {
    const raw = JSON.parse(localStorage.getItem(STORE) ?? '[]')
    return Array.isArray(raw) ? raw.filter((x) => typeof x === 'string') : []
  } catch {
    return []
  }
}
function saveClosed(closed: string[]) {
  try {
    localStorage.setItem(STORE, JSON.stringify(closed))
  } catch {
    // private mode: only this visit remembers
  }
}

const SHOW: Record<Show, (f: Feature) => boolean> = { all: () => true, on: (f) => f.enabled, off: (f) => !f.enabled, changed: (f) => f.changed }
const SORT: Record<Sort, ((a: Feature, b: Feature) => number) | null> = {
  page: null,
  az: (a, b) => a.label.localeCompare(b.label),
  on: (a, b) => Number(b.enabled) - Number(a.enabled) || a.label.localeCompare(b.label),
  off: (a, b) => Number(a.enabled) - Number(b.enabled) || a.label.localeCompare(b.label),
}

/** Admin → Feature switches (PR 99): sections by what they change, each opens and closes; find, filter and sort on top. */
export function FeatureSwitches() {
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [show, setShow] = useState<Show>('all')
  const [sort, setSort] = useState<Sort>('page')
  const [closed, setClosedState] = useState<string[]>(readClosed)
  const [pending, setPending] = useState<Feature | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['features'], queryFn: async () => (await api.get<Data>('/features')).data })

  const setClosed = (next: string[]) => {
    setClosedState(next)
    saveClosed(next)
  }
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
  const reset = useMutation({ mutationFn: (f: Feature) => api.delete(`/features/${f.key}`), onSuccess: refresh, onError: (e) => toast.error(apiErrorMessage(e)) })

  const q = search.trim().toLowerCase()
  const filtering = q !== '' || show !== 'all'
  const groups = useMemo(() => {
    const order = SORT[sort]
    return (data?.groups ?? [])
      .map((g) => {
        const items = g.items.filter((f) => SHOW[show](f) && (!q || `${f.label} ${f.description} ${f.key} ${g.group}`.toLowerCase().includes(q)))
        return { ...g, items: order ? [...items].sort(order) : items, on: g.items.filter((f) => f.enabled).length, total: g.items.length, changed: g.items.filter((f) => f.changed).length }
      })
      .filter((g) => g.items.length)
  }, [data, q, show, sort])
  const shown = groups.reduce((n, g) => n + g.items.length, 0)
  const allNames = (data?.groups ?? []).map((g) => g.group)

  return (
    <Page title='Feature switches' help='switches' description='One switch for every feature. OFF = hidden everywhere, as if it never existed. Data is kept.'>
      <div className='grid gap-3'>
        <div className='flex flex-wrap items-center gap-2'>
          <Input placeholder='Find a switch…' value={search} onChange={(e) => setSearch(e.target.value)} className='h-9 w-full sm:w-64' aria-label='Find a switch' />
          <Select value={show} onValueChange={(v) => setShow(v as Show)}>
            <SelectTrigger className='h-9 w-40' aria-label='Show'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='all'>All switches</SelectItem>
              <SelectItem value='on'>Only ON</SelectItem>
              <SelectItem value='off'>Only OFF</SelectItem>
              <SelectItem value='changed'>Changed by you</SelectItem>
            </SelectContent>
          </Select>
          <Select value={sort} onValueChange={(v) => setSort(v as Sort)}>
            <SelectTrigger className='h-9 w-40' aria-label='Sort'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='page'>Usual order</SelectItem>
              <SelectItem value='az'>A – Z</SelectItem>
              <SelectItem value='on'>ON first</SelectItem>
              <SelectItem value='off'>OFF first</SelectItem>
            </SelectContent>
          </Select>
          <div className='flex gap-1 sm:ms-auto'>
            <Button variant='ghost' size='sm' onClick={() => setClosed([])} disabled={filtering}>
              <ChevronsUpDown /> Open all
            </Button>
            <Button variant='ghost' size='sm' onClick={() => setClosed(allNames)} disabled={filtering}>
              <ChevronsDownUp /> Close all
            </Button>
          </div>
        </div>
        {data && (
          <p className='flex flex-wrap items-center gap-2 text-sm text-muted-foreground'>
            <Badge>{data.summary.on} ON</Badge>
            <Badge variant='secondary'>{data.summary.off} OFF</Badge>
            {data.summary.changed > 0 && <Badge variant='outline'>{data.summary.changed} changed by you</Badge>}
            {filtering && <span>· {shown} shown (all sections open while you search or filter)</span>}
          </p>
        )}
      </div>
      {isLoading && <Skeleton className='h-96' />}
      {data && groups.length === 0 && <p className='text-sm text-muted-foreground'>No switch matches. Try another word or “All switches”.</p>}
      <div className='grid gap-3'>
        {groups.map((g) => {
          const open = filtering || !closed.includes(g.group)
          return (
            <Card key={g.group} className='gap-0 py-0' data-section={g.group}>
              <Collapsible open={open} onOpenChange={(o) => setClosed(o ? closed.filter((c) => c !== g.group) : [...closed, g.group])}>
                <CollapsibleTrigger asChild>
                  <button type='button' className='flex w-full items-center gap-3 px-4 py-3 text-start sm:px-6' disabled={filtering}>
                    <span className='min-w-0 flex-1'>
                      <span className='block font-semibold'>{g.group}</span>
                      <span className='block text-xs text-muted-foreground'>
                        {g.total} switches · {g.on} ON · {g.total - g.on} OFF{g.changed > 0 && ` · ${g.changed} changed`}
                      </span>
                    </span>
                    <ChevronDown className={cn('size-5 shrink-0 text-muted-foreground transition-transform', open && 'rotate-180')} aria-hidden />
                  </button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                  <CardContent className='divide-y border-t px-4 sm:px-6'>
                    {g.items.map((f) => (
                      <div key={f.key} className='flex items-start gap-3 py-3' data-switch={f.key}>
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
                </CollapsibleContent>
              </Collapsible>
            </Card>
          )
        })}
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
