import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { Check } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { Page } from '@/components/layout/page'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'

type City = { id: number; name: string; region: string; enabled: boolean }
type SetupData = {
  settings: {
    ads_expiry_days: number
    ads_max_per_day: number
    ads_max_photos: number
    languages: string[]
    available_languages: { code: string; name: string; default: boolean }[]
    weekly_emails: string[]
    support_email: string
  }
  cities: City[]
  demo_data: boolean
  completed: boolean
}

const STEPS = ['Languages', 'Ad rules', 'Cities', 'Emails', 'Demo data'] as const

export function SetupPage() {
  const { data, isError } = useQuery({ queryKey: ['setup'], queryFn: async () => (await api.get<SetupData>('/setup')).data, retry: false })
  return (
    <Page title='First-time setup' help='start' description='5 short steps. You can change everything later in Settings.'>
      {isError && <p className='text-muted-foreground'>The setup wizard is not available: it is switched OFF (Feature switches), or only the Super Admin can use it.</p>}
      {data && <Wizard initial={data} />}
      {!data && !isError && <Skeleton className='h-80 w-full max-w-2xl' />}
    </Page>
  )
}

function Wizard({ initial }: { initial: SetupData }) {
  const qc = useQueryClient()
  const navigate = useNavigate()
  const load = useAuthStore((s) => s.load)
  const [step, setStep] = useState(0)
  const [languages, setLanguages] = useState(initial.settings.languages)
  const [supportEmail, setSupportEmail] = useState(initial.settings.support_email)
  const [rules, setRules] = useState({
    ads_expiry_days: initial.settings.ads_expiry_days,
    ads_max_per_day: initial.settings.ads_max_per_day,
    ads_max_photos: initial.settings.ads_max_photos,
  })
  const [cities, setCities] = useState<number[]>(initial.cities.filter((c) => c.enabled).map((c) => c.id))
  const [emails, setEmails] = useState(initial.settings.weekly_emails.join('\n'))
  const [demo, setDemo] = useState(initial.demo_data)

  const done = async () => {
    await load()
    qc.invalidateQueries()
    navigate({ to: '/' })
  }

  const finish = useMutation({
    mutationFn: () =>
      api.post('/setup', {
        languages,
        support_email: supportEmail,
        ...rules,
        cities,
        weekly_emails: emails
          .split(/[\s,;]+/)
          .map((e) => e.trim())
          .filter(Boolean),
        demo_data: demo,
      }),
    onSuccess: async () => {
      toast.success('Setup saved. Welcome to iibsom!')
      await done()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const skip = useMutation({
    mutationFn: () => api.post('/setup/skip'),
    onSuccess: done,
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const last = step === STEPS.length - 1
  const canGoOn = step !== 2 || cities.length > 0
  const regions = [...new Set(initial.cities.map((c) => c.region))]

  return (
    <Card className='max-w-2xl'>
      <CardHeader>
        <ol className='flex flex-wrap gap-2 text-xs' aria-label='Steps'>
          {STEPS.map((name, i) => (
            <li key={name} aria-current={i === step ? 'step' : undefined} className={`flex items-center gap-1 rounded-full px-2.5 py-1 ${i === step ? 'bg-primary text-primary-foreground' : i < step ? 'bg-muted text-foreground' : 'text-muted-foreground'}`}>
              {i < step ? <Check className='size-3' /> : <span>{i + 1}.</span>} {name}
            </li>
          ))}
        </ol>
        <CardTitle className='pt-3'>{STEPS[step]}</CardTitle>
        <CardDescription>
          {
            [
              'Which languages the site shows, and where customers can write to you.',
              'How long ads stay up and how much one seller can post.',
              'Only the cities you choose appear on the site and in the post form.',
              'Who gets the weekly summary email every Monday morning.',
              'Demo ads make the site look full while you test. Hide them before going live.',
            ][step]
          }
        </CardDescription>
      </CardHeader>

      <CardContent className='grid gap-5'>
        {step === 0 && (
          <>
            <div className='grid gap-2'>
              <Label>Languages ON</Label>
              {initial.settings.available_languages.map((l) => (
                <label key={l.code} className='flex items-center gap-2 text-sm'>
                  <Checkbox
                    checked={l.default || languages.includes(l.code)}
                    disabled={l.default}
                    onCheckedChange={(v) => setLanguages(v ? [...new Set([...languages, l.code])] : languages.filter((c) => c !== l.code))}
                  />
                  {l.name} {l.default && <span className='text-muted-foreground'>(always ON)</span>}
                </label>
              ))}
              <p className='text-xs text-muted-foreground'>Turn Somali ON after a native speaker has checked the texts (docs/somali-review).</p>
            </div>
            <div className='grid gap-2'>
              <Label htmlFor='setup-support'>Help email for customers</Label>
              <Input id='setup-support' type='email' className='max-w-md' value={supportEmail} onChange={(e) => setSupportEmail(e.target.value)} placeholder='help@iibsom.com' />
              <p className='text-xs text-muted-foreground'>Shown on the Help page. Empty = not shown.</p>
            </div>
          </>
        )}

        {step === 1 && (
          <div className='grid gap-4 sm:grid-cols-3'>
            {(
              [
                ['ads_expiry_days', 'Days an ad stays up', 7, 365],
                ['ads_max_per_day', 'Ads per seller per day', 1, 100],
                ['ads_max_photos', 'Photos per ad', 1, 20],
              ] as const
            ).map(([key, label, min, max]) => (
              <div key={key} className='grid gap-2'>
                <Label htmlFor={`setup-${key}`}>{label}</Label>
                <Input id={`setup-${key}`} type='number' min={min} max={max} value={rules[key]} onChange={(e) => setRules({ ...rules, [key]: Number(e.target.value) })} />
                <p className='text-xs text-muted-foreground'>
                  {min}–{max}
                </p>
              </div>
            ))}
          </div>
        )}

        {step === 2 && (
          <div className='grid gap-4'>
            <p className='text-sm'>
              {cities.length} of {initial.cities.length} cities ON.
            </p>
            {regions.map((region) => (
              <fieldset key={region} className='grid gap-2'>
                <legend className='mb-1 text-sm font-medium'>{region}</legend>
                <div className='grid grid-cols-2 gap-2 sm:grid-cols-3'>
                  {initial.cities
                    .filter((c) => c.region === region)
                    .map((c) => (
                      <label key={c.id} className='flex items-center gap-2 text-sm'>
                        <Checkbox checked={cities.includes(c.id)} onCheckedChange={(v) => setCities(v ? [...cities, c.id] : cities.filter((id) => id !== c.id))} />
                        {c.name}
                      </label>
                    ))}
                </div>
              </fieldset>
            ))}
            {cities.length === 0 && <p className='text-sm text-destructive'>Choose at least one city.</p>}
          </div>
        )}

        {step === 3 && (
          <div className='grid gap-2'>
            <Label htmlFor='setup-emails'>Weekly summary email to (up to 5, one per line)</Label>
            <Textarea id='setup-emails' rows={3} className='max-w-md text-sm' value={emails} onChange={(e) => setEmails(e.target.value)} placeholder='owner@iibsom.com' />
            <p className='text-xs text-muted-foreground'>New ads, users and reports from last week. Empty = no email.</p>
          </div>
        )}

        {step === 4 && (
          <label className='flex items-center justify-between gap-4 rounded-lg border p-4'>
            <span>
              <span className='block font-medium'>Show demo data</span>
              <span className='block text-sm text-muted-foreground'>OFF = demo ads are hidden everywhere. Delete them in Settings → Demo data before going live.</span>
            </span>
            <Switch checked={demo} onCheckedChange={setDemo} aria-label='Show demo data' />
          </label>
        )}
      </CardContent>

      <CardFooter className='flex flex-wrap justify-between gap-2'>
        <Button variant='ghost' disabled={skip.isPending || finish.isPending} onClick={() => (initial.completed ? navigate({ to: '/' }) : skip.mutate())}>
          {initial.completed ? 'Close' : 'Later'}
        </Button>
        <div className='flex gap-2'>
          {step > 0 && (
            <Button variant='outline' onClick={() => setStep(step - 1)}>
              Back
            </Button>
          )}
          {last ? (
            <Button disabled={finish.isPending} onClick={() => finish.mutate()}>
              Finish
            </Button>
          ) : (
            <Button disabled={!canGoOn} onClick={() => setStep(step + 1)}>
              Next
            </Button>
          )}
        </div>
      </CardFooter>
    </Card>
  )
}
