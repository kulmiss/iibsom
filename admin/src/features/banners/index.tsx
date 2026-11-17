import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ImagePlus, Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { Page } from '@/components/layout/page'

type Placement = 'slider' | 'home' | 'list' | 'ad'
type Banner = {
  id: number
  title_en: string
  title_so: string | null
  link_url: string | null
  enabled: boolean
  sort: number
  image: string | null
  starts_at: string | null
  ends_at: string | null
  live: boolean
  placement: Placement
  advertiser: string | null
  category_id: number | null
  category: string | null
  city_id: number | null
  city: string | null
  clicks: number
  clicks_30: number
}
type Options = { banner_ads: boolean; categories: { id: number; name: string }[]; cities: { id: number; name: string }[] }

const PLACEMENT: Record<Placement, string> = {
  slider: 'Home slider',
  home: 'Ad: home page',
  list: 'Ad: lists of ads',
  ad: 'Ad: ad pages',
}

export function Banners() {
  const qc = useQueryClient()
  const [editing, setEditing] = useState<Banner | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Banner | null>(null)
  const { data } = useQuery({ queryKey: ['banners'], queryFn: async () => (await api.get<Banner[]>('/banners')).data })
  const { data: options } = useQuery({ queryKey: ['banner-options'], queryFn: async () => (await api.get<Options>('/banners/options')).data })
  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/banners/${id}`),
    onSuccess: () => {
      toast.success('Banner deleted.')
      setDeleting(null)
      qc.invalidateQueries({ queryKey: ['banners'] })
    },
  })

  return (
    <Page
      title='Home banners'
      help='banners'
      description={options?.banner_ads ? 'Home slider pictures (1600 × 500) and paid banner ads on the home page, lists and ad pages (1200 × 300 wide, 600 × 500 on ad pages), with click counts.' : 'Pictures at the top of the home page. Best size: 1600 × 500 pixels.'}
      actions={
        <Button onClick={() => setEditing('new')}>
          <Plus /> Add banner
        </Button>
      }
    >
      <div className='grid gap-4 md:grid-cols-2'>
        {data?.map((b) => (
          <Card key={b.id} className='overflow-hidden py-0'>
            {b.image ? <img src={b.image} alt='' className='aspect-[16/5] w-full object-cover' /> : <div className='aspect-[16/5] bg-muted' />}
            <CardContent className='flex items-center gap-2 py-3'>
              <div className='min-w-0 flex-1'>
                <p className='truncate font-medium'>{b.title_en}</p>
                <p className='truncate text-xs text-muted-foreground'>
                  {b.link_url ?? 'No link'} {b.starts_at && `· from ${b.starts_at}`} {b.ends_at && `· until ${b.ends_at}`}
                </p>
                <p className='truncate text-xs text-muted-foreground'>
                  {PLACEMENT[b.placement]}
                  {b.advertiser && ` · ${b.advertiser}`}
                  {b.placement !== 'slider' && ` · ${b.category ?? 'All categories'} · ${b.city ?? 'All cities'}`}
                  {` · ${b.clicks_30} clicks (30 days), ${b.clicks} in all`}
                </p>
              </div>
              <Badge variant={b.live ? 'default' : 'secondary'}>{b.live ? 'Showing' : 'Not showing'}</Badge>
              <Button size='icon' variant='ghost' className='size-8' aria-label='Edit banner' onClick={() => setEditing(b)}>
                <Pencil />
              </Button>
              <Button size='icon' variant='ghost' className='size-8 text-destructive' aria-label='Delete banner' onClick={() => setDeleting(b)}>
                <Trash2 />
              </Button>
            </CardContent>
          </Card>
        ))}
        {data?.length === 0 && <p className='text-muted-foreground'>No banners yet.</p>}
      </div>
      <BannerDialog key={editing === 'new' ? 'new' : (editing?.id ?? 'closed')} banner={editing} options={options} onClose={() => setEditing(null)} />
      <ConfirmDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)} title='Delete this banner?' desc='It is removed from the home page.' confirmText='Delete' destructive handleConfirm={() => deleting && remove.mutate(deleting.id)} />
    </Page>
  )
}

function BannerDialog({ banner, options, onClose }: { banner: Banner | 'new' | null; options?: Options; onClose: () => void }) {
  const qc = useQueryClient()
  const b = banner && banner !== 'new' ? banner : null
  const [form, setForm] = useState({ title_en: b?.title_en ?? '', title_so: b?.title_so ?? '', link_url: b?.link_url ?? '', enabled: b?.enabled ?? true, sort: b?.sort ?? 0, starts_at: b?.starts_at ?? '', ends_at: b?.ends_at ?? '' })
  const [ad, setAd] = useState({ placement: b?.placement ?? 'slider', advertiser: b?.advertiser ?? '', category_id: b?.category_id ? String(b.category_id) : '', city_id: b?.city_id ? String(b.city_id) : '' })
  const paid = ad.placement !== 'slider'
  const [file, setFile] = useState<File | null>(null)

  const save = useMutation({
    mutationFn: () => {
      const body = new FormData()
      Object.entries(form).forEach(([k, v]) => body.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v)))
      if (file) body.append('image', file)
      // Paid-slot fields only while the "Banner ads" switch is ON (otherwise the banner keeps its place).
      if (options?.banner_ads) {
        body.append('placement', ad.placement)
        body.append('advertiser', ad.advertiser)
        if (paid && ad.category_id) body.append('category_id', ad.category_id)
        if (paid && ad.city_id) body.append('city_id', ad.city_id)
      }
      return api.post(b ? `/banners/${b.id}` : '/banners', body)
    },
    onSuccess: () => {
      toast.success('Banner saved.')
      qc.invalidateQueries({ queryKey: ['banners'] })
      onClose()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <Dialog open={banner !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>{b ? 'Edit banner' : 'Add a banner'}</DialogTitle>
        </DialogHeader>
        <div className='grid gap-3'>
          <label className='flex cursor-pointer items-center gap-2 rounded-md border border-dashed p-3 text-sm'>
            <ImagePlus className='size-5' /> {file ? file.name : b ? 'Change picture (optional)' : 'Choose a picture (JPG, PNG or WebP)'}
            <input type='file' accept='image/jpeg,image/png,image/webp' className='sr-only' onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
          </label>
          <div className='grid gap-2'>
            <Label htmlFor='bn-title'>Title (also read by screen readers)</Label>
            <Input id='bn-title' value={form.title_en} onChange={(e) => setForm({ ...form, title_en: e.target.value })} maxLength={100} />
          </div>
          <div className='grid gap-2'>
            <Label htmlFor='bn-link'>Link</Label>
            <Input id='bn-link' value={form.link_url} onChange={(e) => setForm({ ...form, link_url: e.target.value })} placeholder='/post or https://…' />
          </div>
          <div className='grid gap-3 sm:grid-cols-2'>
            <div className='grid min-w-0 gap-2'>
              <Label htmlFor='bn-from'>Show from</Label>
              <Input id='bn-from' type='date' className='min-w-0' value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} />
            </div>
            <div className='grid min-w-0 gap-2'>
              <Label htmlFor='bn-to'>Until</Label>
              <Input id='bn-to' type='date' className='min-w-0' value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} />
            </div>
          </div>
          {options?.banner_ads && (
            <div className='grid gap-3 rounded-md border p-3'>
              <div className='grid gap-2'>
                <Label htmlFor='bn-place'>Where it shows</Label>
                <select id='bn-place' className='h-9 w-full min-w-0 rounded-md border bg-transparent px-2 text-sm' value={ad.placement} onChange={(e) => setAd({ ...ad, placement: e.target.value as Placement })}>
                  {(Object.keys(PLACEMENT) as Placement[]).map((p) => (
                    <option key={p} value={p}>
                      {PLACEMENT[p]}
                    </option>
                  ))}
                </select>
              </div>
              {paid && (
                <>
                  <div className='grid gap-2'>
                    <Label htmlFor='bn-adv'>Advertiser</Label>
                    <Input id='bn-adv' value={ad.advertiser} onChange={(e) => setAd({ ...ad, advertiser: e.target.value })} maxLength={100} placeholder='Business name' />
                  </div>
                  <div className='grid grid-cols-2 gap-3'>
                    <div className='grid gap-2'>
                      <Label htmlFor='bn-cat'>Only in category</Label>
                      <select id='bn-cat' className='h-9 w-full min-w-0 rounded-md border bg-transparent px-2 text-sm' value={ad.category_id} onChange={(e) => setAd({ ...ad, category_id: e.target.value })}>
                        <option value=''>All categories</option>
                        {options.categories.map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.name}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className='grid gap-2'>
                      <Label htmlFor='bn-city'>Only in city</Label>
                      <select id='bn-city' className='h-9 w-full min-w-0 rounded-md border bg-transparent px-2 text-sm' value={ad.city_id} onChange={(e) => setAd({ ...ad, city_id: e.target.value })}>
                        <option value=''>All cities</option>
                        {options.cities.map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.name}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                  <p className='text-xs text-muted-foreground'>Paid banners need a link. They show with a small &quot;Ad&quot; label.</p>
                </>
              )}
            </div>
          )}
          <label className='flex items-center justify-between text-sm'>
            ON <Switch checked={form.enabled} onCheckedChange={(v) => setForm({ ...form, enabled: v })} />
          </label>
        </div>
        <DialogFooter>
          <Button onClick={() => save.mutate()} disabled={!form.title_en || (!b && !file) || (paid && !form.link_url) || save.isPending}>
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
