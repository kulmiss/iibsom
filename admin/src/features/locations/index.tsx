import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MapPin, Plus } from 'lucide-react'
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

type City = { id: number; region_id: number; name_en: string; name_so: string | null; slug: string; enabled: boolean; popular: boolean; ads_count: number; areas_count: number }
type Region = { id: number; name: string; enabled: boolean; cities: City[] }
type Area = { id: number; name_en: string; name_so: string | null; enabled: boolean }

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
    <Page title='Cities and areas' description={`Only cities that are ON appear on the site. ${on} ON now.`}>
      {isLoading && <Skeleton className='h-96' />}
      <div className='grid gap-4 lg:grid-cols-2'>
        {data?.map((r) => (
          <Card key={r.id} className={r.enabled ? undefined : 'opacity-70'}>
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
                  <span className='flex-1 font-medium'>
                    {c.name_en} {c.popular && <Badge variant='secondary'>Popular</Badge>}
                  </span>
                  <span className='text-xs text-muted-foreground'>{c.ads_count} ads</span>
                  <Button size='sm' variant='ghost' onClick={() => setAreasFor(c)}>
                    {c.areas_count} areas
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
  const [name, setName] = useState('')
  const { data } = useQuery({ queryKey: ['areas', city?.id], queryFn: async () => (await api.get<Area[]>(`/cities/${city!.id}/areas`)).data, enabled: !!city })
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['areas'] })
    onChanged()
  }
  const add = useMutation({
    mutationFn: () => api.post(`/cities/${city!.id}/areas`, { name_en: name }),
    onSuccess: () => {
      setName('')
      refresh()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const toggle = useMutation({ mutationFn: (a: Area) => api.put(`/areas/${a.id}`, { enabled: !a.enabled }), onSuccess: refresh })

  return (
    <Sheet open={!!city} onOpenChange={onOpenChange}>
      <SheetContent className='flex w-full flex-col gap-0 p-0 sm:max-w-md'>
        <SheetHeader className='border-b'>
          <SheetTitle>Areas in {city?.name_en}</SheetTitle>
          <SheetDescription>Sellers pick one of these (optional). Turn off ones you don't need.</SheetDescription>
        </SheetHeader>
        <ScrollArea className='min-h-0 flex-1'>
          <ul className='divide-y'>
            {data?.map((a) => (
              <li key={a.id} className='flex items-center justify-between px-4 py-2'>
                <span className={a.enabled ? '' : 'text-muted-foreground line-through'}>{a.name_en}</span>
                <Switch checked={a.enabled} onCheckedChange={() => toggle.mutate(a)} aria-label={`${a.name_en} on or off`} />
              </li>
            ))}
          </ul>
        </ScrollArea>
        <form
          className='flex gap-2 border-t p-3'
          onSubmit={(e) => {
            e.preventDefault()
            add.mutate()
          }}
        >
          <Input placeholder='New area, e.g. KM4' value={name} onChange={(e) => setName(e.target.value)} />
          <Button type='submit' disabled={name.trim().length < 2 || add.isPending}>
            <Plus /> Add
          </Button>
        </form>
      </SheetContent>
    </Sheet>
  )
}
