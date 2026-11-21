import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MapPin, Plus, ChevronUp, ChevronDown, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Page } from '@/components/layout/page'

type City = { id: number; region_id: number; name_en: string; name_so: string | null; slug: string; enabled: boolean; popular: boolean; ads_count: number; areas_count: number; areas_review: number }
type Region = { id: number; name: string; enabled: boolean; cities: City[] }
type Area = { id: number; name_en: string; name_so: string | null; enabled: boolean; needs_review: boolean; ads_count: number }

export function Locations() {
  const qc = useQueryClient()
  const [areasFor, setAreasFor] = useState<City | null>(null)
  const [addCityIn, setAddCityIn] = useState<Region | null>(null)
  const [cityName, setCityName] = useState('')

  const { data, isLoading } = useQuery({ queryKey: ['locations'], queryFn: async () => (await api.get<Region[]>('/locations')).data })
  const refresh = () => qc.invalidateQueries({ queryKey: ['locations'] })
  const onError = (e: unknown) => toast.error(apiErrorMessage(e))

  const updateCity = useMutation({ mutationFn: ({ id, ...body }: { id: number; enabled?: boolean; popular?: boolean }) => api.put(`/cities/${id}`, body), onSuccess: refresh, onError })
  const updateRegion = useMutation({ mutationFn: ({ id, enabled }: { id: number; enabled: boolean }) => api.put(`/regions/${id}`, { enabled }), onSuccess: refresh, onError })
  const addCity = useMutation({
    mutationFn: () => api.post('/cities', { region_id: addCityIn!.id, name_en: cityName }),
    onSuccess: () => {
      toast.success('City added (OFF). Switch it on when you are ready.')
      setAddCityIn(null)
      setCityName('')
      refresh()
    },
    onError,
  })

  const on = data?.flatMap((r) => r.cities).filter((c) => c.enabled).length ?? 0

  return (
    <Page title='Cities and districts' help='locations' description={`Only cities that are ON appear on the site. ${on} ON now.`}>
      {isLoading && <Skeleton className='h-96' />}
      <div className='grid gap-4 lg:grid-cols-2'>
        {data?.map((r) => (
          <Card key={r.id} className={r.enabled ? 'min-w-0' : 'min-w-0 opacity-70'}>
            <CardHeader className='flex flex-row items-center justify-between gap-2 space-y-0'>
              <CardTitle className='text-base'>{r.name}</CardTitle>
              <label className='flex items-center gap-2 text-xs text-muted-foreground'>
                Region <Switch checked={r.enabled} onCheckedChange={(enabled) => updateRegion.mutate({ id: r.id, enabled })} aria-label={`${r.name} on or off`} />
              </label>
            </CardHeader>
            <CardContent className='space-y-1'>
              {r.cities.map((c) => (
                <div key={c.id} className='flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted/50'>
                  <MapPin className='size-4 text-muted-foreground' />
                  <span className='min-w-0 flex-1 truncate font-medium'>
                    {c.name_en} {c.popular && <Badge variant='secondary'>Popular</Badge>}
                  </span>
                  <span className='hidden text-xs text-muted-foreground sm:inline'>{c.ads_count} ads</span>
                  <Button size='sm' variant='ghost' onClick={() => setAreasFor(c)} data-areas-button={c.name_en} aria-label={`${c.areas_count} districts in ${c.name_en}${c.areas_review ? `, ${c.areas_review} to check` : ''}`}>
                    {c.areas_count} <span className='hidden sm:inline'>districts</span>
                    {c.areas_review > 0 && (
                      <Badge variant='secondary' className='bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100' title={`${c.areas_review} names not checked by a native speaker yet`}>
                        {c.areas_review}
                        <span className='hidden sm:inline'>&nbsp;to check</span>
                      </Badge>
                    )}
                  </Button>
                  <Switch checked={c.enabled} onCheckedChange={(enabled) => updateCity.mutate({ id: c.id, enabled }, { onSuccess: () => toast.success(`${c.name_en} is ${enabled ? 'ON' : 'OFF'}.`) })} aria-label={`${c.name_en} on or off`} />
                </div>
              ))}
              <Button size='sm' variant='ghost' onClick={() => setAddCityIn(r)}>
                <Plus /> Add city
              </Button>
            </CardContent>
          </Card>
        ))}
      </div>

      <Dialog open={!!addCityIn} onOpenChange={(o) => !o && setAddCityIn(null)}>
        <DialogContent className='sm:max-w-sm'>
          <DialogHeader>
            <DialogTitle>Add a city in {addCityIn?.name}</DialogTitle>
          </DialogHeader>
          <div className='grid gap-2'>
            <Label htmlFor='city-name'>City name</Label>
            <Input id='city-name' value={cityName} onChange={(e) => setCityName(e.target.value)} />
          </div>
          <DialogFooter>
            <Button onClick={() => addCity.mutate()} disabled={cityName.trim().length < 2 || addCity.isPending}>
              Add (OFF)
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AreasSheet city={areasFor} onOpenChange={(o) => !o && setAreasFor(null)} onChanged={refresh} />
    </Page>
  )
}

function AreasSheet({ city, onOpenChange, onChanged }: { city: City | null; onOpenChange: (o: boolean) => void; onChanged: () => void }) {
  const qc = useQueryClient()
  const [nameEn, setNameEn] = useState('')
  const [nameSo, setNameSo] = useState('')
  const [editing, setEditing] = useState<Area | null>(null)
  const [edit, setEdit] = useState({ en: '', so: '' })
  const { data } = useQuery({ queryKey: ['areas', city?.id], queryFn: async () => (await api.get<Area[]>(`/cities/${city!.id}/areas`)).data, enabled: !!city })
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['areas'] })
    onChanged()
  }
  const onError = (e: unknown) => toast.error(apiErrorMessage(e))
  const add = useMutation({
    mutationFn: () => api.post(`/cities/${city!.id}/areas`, { name_en: nameEn, name_so: nameSo || null }),
    onSuccess: () => {
      setNameEn('')
      setNameSo('')
      refresh()
    },
    onError,
  })
  const update = useMutation({
    mutationFn: ({ id, ...body }: { id: number; enabled?: boolean; name_en?: string; name_so?: string | null; reviewed?: boolean }) => api.put(`/areas/${id}`, body),
    onSuccess: () => {
      setEditing(null)
      refresh()
    },
    onError,
  })
  const move = useMutation({ mutationFn: ({ id, direction }: { id: number; direction: 'up' | 'down' }) => api.post(`/areas/${id}/move`, { direction }), onSuccess: refresh, onError })
  const remove = useMutation({ mutationFn: (a: Area) => api.delete(`/areas/${a.id}`), onSuccess: refresh, onError })
  const list = data ?? []

  return (
    <Sheet open={!!city} onOpenChange={onOpenChange}>
      <SheetContent className='flex w-full flex-col gap-0 p-0 sm:max-w-lg'>
        <SheetHeader className='border-b'>
          <SheetTitle>Districts in {city?.name_en}</SheetTitle>
          <SheetDescription>Sellers pick one (optional); buyers filter by it on Property and Vehicles lists. Yellow = names not checked by a native speaker yet.</SheetDescription>
        </SheetHeader>
        <ScrollArea className='min-h-0 flex-1'>
          <ul className='divide-y'>
            {list.map((a, i) => (
              <li key={a.id} className='flex items-center gap-2 px-4 py-2' data-area={a.name_en}>
                <div className='flex flex-col'>
                  <Button size='icon' variant='ghost' className='size-6' disabled={i === 0 || move.isPending} onClick={() => move.mutate({ id: a.id, direction: 'up' })} aria-label={`Move ${a.name_en} up`}>
                    <ChevronUp />
                  </Button>
                  <Button size='icon' variant='ghost' className='size-6' disabled={i === list.length - 1 || move.isPending} onClick={() => move.mutate({ id: a.id, direction: 'down' })} aria-label={`Move ${a.name_en} down`}>
                    <ChevronDown />
                  </Button>
                </div>
                <button type='button' className='min-w-0 flex-1 text-start' onClick={() => { setEditing(a); setEdit({ en: a.name_en, so: a.name_so ?? '' }) }}>
                  <span className={a.enabled ? 'block font-medium' : 'block font-medium text-muted-foreground line-through'}>{a.name_en}</span>
                  <span className='block text-xs text-muted-foreground'>
                    {a.name_so ?? 'no Somali name'} · {a.ads_count} ads
                  </span>
                </button>
                {a.needs_review && (
                  <Badge variant='secondary' className='bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100' data-needs-review>
                    check names
                  </Badge>
                )}
                <Switch checked={a.enabled} onCheckedChange={() => update.mutate({ id: a.id, enabled: !a.enabled })} aria-label={`${a.name_en} on or off`} />
              </li>
            ))}
          </ul>
        </ScrollArea>
        <form
          className='grid gap-2 border-t p-3 sm:grid-cols-[1fr_1fr_auto]'
          onSubmit={(e) => {
            e.preventDefault()
            add.mutate()
          }}
        >
          <Input placeholder='English name, e.g. KM4' value={nameEn} onChange={(e) => setNameEn(e.target.value)} aria-label='English name' />
          <Input placeholder='Somali name' value={nameSo} onChange={(e) => setNameSo(e.target.value)} aria-label='Somali name' />
          <Button type='submit' disabled={nameEn.trim().length < 2 || add.isPending}>
            <Plus /> Add
          </Button>
        </form>
      </SheetContent>

      <Dialog open={!!editing} onOpenChange={(o) => !o && setEditing(null)}>
        <DialogContent className='sm:max-w-sm'>
          <DialogHeader>
            <DialogTitle>Edit district</DialogTitle>
          </DialogHeader>
          <div className='grid gap-3'>
            <div className='grid gap-1.5'>
              <Label htmlFor='area-en'>English name</Label>
              <Input id='area-en' value={edit.en} onChange={(e) => setEdit({ ...edit, en: e.target.value })} />
            </div>
            <div className='grid gap-1.5'>
              <Label htmlFor='area-so'>Somali name</Label>
              <Input id='area-so' value={edit.so} onChange={(e) => setEdit({ ...edit, so: e.target.value })} />
            </div>
            <p className='text-xs text-muted-foreground'>Saving marks the names as checked.</p>
          </div>
          <DialogFooter className='gap-2 sm:justify-between'>
            {editing && editing.ads_count === 0 ? (
              <Button variant='ghost' className='text-destructive' onClick={() => window.confirm(`Delete ${editing.name_en}?`) && remove.mutate(editing, { onSuccess: () => setEditing(null) })}>
                <Trash2 /> Delete
              </Button>
            ) : (
              <span className='text-xs text-muted-foreground'>Used by ads: turn it off instead of deleting.</span>
            )}
            <div className='flex gap-2'>
              {editing?.needs_review && (
                <Button variant='outline' onClick={() => update.mutate({ id: editing.id, reviewed: true })}>
                  Names are right
                </Button>
              )}
              <Button disabled={edit.en.trim().length < 2 || update.isPending} onClick={() => editing && update.mutate({ id: editing.id, name_en: edit.en, name_so: edit.so.trim() || null })}>
                Save
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Sheet>
  )
}
