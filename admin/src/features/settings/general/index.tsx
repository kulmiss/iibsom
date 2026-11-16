import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { ContentSection } from '../components/content-section'

type General = {
  ads_expiry_days: number
  ads_max_per_day: number
  ads_max_photos: number
  languages: string[]
  available_languages: { code: string; name: string; default: boolean }[]
  currency: string
  bad_words: string
}

export function SettingsGeneral() {
  const { data } = useQuery({ queryKey: ['settings-general'], queryFn: async () => (await api.get<General>('/settings/general')).data })
  if (!data) return <Skeleton className='h-64 w-full' />
  return <GeneralForm initial={data} />
}

function GeneralForm({ initial }: { initial: General }) {
  const qc = useQueryClient()
  const [form, setForm] = useState<General>(initial)

  const save = useMutation({
    mutationFn: () => api.put('/settings/general', form),
    onSuccess: () => {
      toast.success('Settings saved.')
      qc.invalidateQueries({ queryKey: ['settings-general'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const num = (key: 'ads_expiry_days' | 'ads_max_per_day' | 'ads_max_photos', label: string, help: string) => (
    <div className='grid gap-2'>
      <Label htmlFor={key}>{label}</Label>
      <Input id={key} type='number' className='max-w-32' value={form[key]} onChange={(e) => setForm({ ...form, [key]: Number(e.target.value) })} />
      <p className='text-xs text-muted-foreground'>{help}</p>
    </div>
  )

  return (
    <ContentSection title='General' desc='Rules for ads and which languages are ON.'>
      <form
        className='space-y-6'
        onSubmit={(e) => {
          e.preventDefault()
          save.mutate()
        }}
      >
        {num('ads_expiry_days', 'Ads end after (days)', 'Sellers can renew an ad when it ends. 7 to 365.')}
        {num('ads_max_per_day', 'Ads per person per day', 'Stops spam. 1 to 100.')}
        {num('ads_max_photos', 'Photos per ad', '1 to 20.')}
        <div className='grid gap-2'>
          <Label>Languages ON</Label>
          {form.available_languages.map((l) => (
            <label key={l.code} className='flex items-center gap-2 text-sm'>
              <Checkbox
                checked={form.languages.includes(l.code)}
                disabled={l.default}
                onCheckedChange={(v) => setForm({ ...form, languages: v ? [...form.languages, l.code] : form.languages.filter((c) => c !== l.code) })}
              />
              {l.name} {l.default && <span className='text-muted-foreground'>(main language, always ON)</span>}
            </label>
          ))}
          <p className='text-xs text-muted-foreground'>When 2 or more are ON, visitors see a language switch.</p>
        </div>
        <div className='grid gap-2'>
          <Label htmlFor='bad_words'>Bad words (one per line)</Label>
          <Textarea id='bad_words' rows={6} className='max-w-md font-mono text-xs' value={form.bad_words} onChange={(e) => setForm({ ...form, bad_words: e.target.value })} />
          <p className='text-xs text-muted-foreground'>Ads with these words are refused (whole words, any case). Add Somali words too. Switch: Safety → Bad word filter.</p>
        </div>
        <p className='text-sm text-muted-foreground'>Currency: {form.currency} (decision #3).</p>
        <Button type='submit' disabled={save.isPending}>
          Save settings
        </Button>
      </form>
    </ContentSection>
  )
}
