import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, Navigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { ContentSection } from '../components/content-section'

type General = {
  ads_expiry_days: number
  ads_max_per_day: number
  ads_max_photos: number
  reports_auto_hide_at: number
  languages: string[]
  available_languages: { code: string; name: string; default: boolean }[]
  currency: string
  bad_words: string
  drug_words: string
  weekly_emails: string[]
  weekly_last_sent: string | null
  support_email: string
  payments_test_mode: boolean
  payment_numbers: Record<string, string>
  payment_methods: Record<string, string>
  diaspora_rates: Record<string, number | string>
  diaspora_rates_date: string | null
  diaspora_currencies: Record<string, string>
  footer_social: Record<string, string>
  footer_networks: Record<string, string>
  footer_links: string[]
  footer_link_options: Record<string, string>
}

export function SettingsGeneral() {
  // Staff without "settings.manage" (moderator, support) only have their own settings: open those.
  const canManage = useAuthStore((st) => st.can('settings.manage'))
  const { data } = useQuery({ queryKey: ['settings-general'], queryFn: async () => (await api.get<General>('/settings/general')).data, enabled: canManage })
  if (!canManage) return <Navigate to='/settings/appearance' replace />
  if (!data) return <Skeleton className='h-64 w-full' />
  return <GeneralForm initial={data} />
}

function GeneralForm({ initial }: { initial: General }) {
  const qc = useQueryClient()
  const [form, setForm] = useState<General>(initial)
  const boostsOn = useAuthStore((st) => !!st.user?.boosts)
  const diasporaOn = useAuthStore((st) => !!st.user?.diaspora)
  const [emails, setEmails] = useState(initial.weekly_emails.join('\n'))

  const save = useMutation({
    mutationFn: () =>
      api.put('/settings/general', {
        ...form,
        weekly_emails: emails
          .split(/[\s,;]+/)
          .map((e) => e.trim())
          .filter(Boolean),
      }),
    onSuccess: () => {
      toast.success('Settings saved.')
      qc.invalidateQueries({ queryKey: ['settings-general'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const sendTest = useMutation({
    mutationFn: async () => (await api.post<{ message: string }>('/settings/weekly-summary/test')).data,
    onSuccess: (d) => toast.success(d.message),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const num = (key: 'ads_expiry_days' | 'ads_max_per_day' | 'ads_max_photos' | 'reports_auto_hide_at', label: string, help: string) => (
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
        {num('reports_auto_hide_at', 'Hide an ad after this many reports', 'Reports from different signed-in people (guests do not count). The ad waits for a staff check in Waiting for approval; it is never deleted and nobody is banned. 2 to 50. Switch: "Hide ads after reports".')}
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
        <div className='grid gap-2'>
          <Label htmlFor='drug_words'>Drug words (one per line)</Label>
          <Textarea id='drug_words' rows={8} className='max-w-md font-mono text-xs' value={form.drug_words} onChange={(e) => setForm({ ...form, drug_words: e.target.value })} />
          <p className='text-xs text-muted-foreground'>
            New and edited ads with these words are refused (whole words, any case: &quot;chat&quot; and &quot;Qatar&quot; are fine). Avoid
            normal words such as &quot;qaad&quot; (take) or &quot;jaad&quot; (kind). Switch: Safety → No drugs. After changes, run{' '}
            <code>php artisan iibsom:drugs:check</code> to find live ads that match.
          </p>
        </div>
        <div className='grid gap-2'>
          <Label htmlFor='weekly_emails'>Weekly summary email to (up to 5 addresses, one per line)</Label>
          <Textarea id='weekly_emails' rows={3} className='max-w-md text-sm' value={emails} onChange={(e) => setEmails(e.target.value)} placeholder='owner@iibsom.com' />
          <p className='text-xs text-muted-foreground'>
            Every Monday at 07:00 (Mogadishu): last week&apos;s new ads, users, reports and top categories. Empty = no email.
            {form.weekly_last_sent && ` Last sent: week ${form.weekly_last_sent}.`}
          </p>
          <div>
            <Button type='button' variant='outline' size='sm' disabled={sendTest.isPending} onClick={() => sendTest.mutate()}>
              Send me a test now
            </Button>
          </div>
        </div>
        <div className='grid gap-2'>
          <Label htmlFor='support_email'>Help email for customers</Label>
          <Input id='support_email' type='email' className='max-w-md' value={form.support_email} onChange={(e) => setForm({ ...form, support_email: e.target.value })} placeholder='help@iibsom.com' />
          <p className='text-xs text-muted-foreground'>Shown at the bottom of the Help page (&quot;Still need help?&quot;) and as &quot;Contact us&quot; in the footer. Empty = not shown.</p>
        </div>
        {boostsOn && (
          <fieldset className='grid gap-3 rounded-lg border p-4'>
            <legend className='px-1 text-sm font-medium'>Payments for boosts</legend>
            <label className='flex items-start justify-between gap-4'>
              <span>
                <span className='block font-medium'>Test mode</span>
                <span className='block text-xs text-muted-foreground'>ON: sellers see &quot;Do not send real money&quot; and payments stay out of the real books. Turn OFF only when the merchant numbers below are real.</span>
              </span>
              <Switch checked={form.payments_test_mode} onCheckedChange={(v) => setForm({ ...form, payments_test_mode: v })} aria-label='Test mode' />
            </label>
            <div className='grid gap-3 sm:grid-cols-2'>
              {Object.entries(form.payment_methods).map(([key, label]) => (
                <div key={key} className='grid gap-1.5'>
                  <Label htmlFor={`pay-${key}`}>{label} merchant number</Label>
                  <Input id={`pay-${key}`} inputMode='tel' value={form.payment_numbers[key] ?? ''} onChange={(e) => setForm({ ...form, payment_numbers: { ...form.payment_numbers, [key]: e.target.value } })} placeholder='Empty = not offered' />
                </div>
              ))}
            </div>
          </fieldset>
        )}
        {diasporaOn && (
          <fieldset className='grid gap-3 rounded-lg border p-4'>
            <legend className='px-1 text-sm font-medium'>Prices for buyers abroad</legend>
            <p className='text-xs text-muted-foreground'>
              How much <strong>1 US dollar</strong> is in each money (for example 0.92 for the euro). Buyers abroad see &quot;≈ €5,980&quot; under the price. 0 or empty = not shown. Update them now and then.
              {form.diaspora_rates_date && ` Last changed: ${form.diaspora_rates_date}.`}
            </p>
            <div className='grid gap-3 sm:grid-cols-2'>
              {Object.entries(form.diaspora_currencies).map(([code, name]) => (
                <div key={code} className='grid gap-1.5'>
                  <Label htmlFor={`rate-${code}`}>
                    {name} ({code})
                  </Label>
                  <Input id={`rate-${code}`} inputMode='decimal' value={form.diaspora_rates[code] ?? ''} onChange={(e) => setForm({ ...form, diaspora_rates: { ...form.diaspora_rates, [code]: e.target.value } })} placeholder='0 = not shown' />
                </div>
              ))}
            </div>
          </fieldset>
        )}
        <fieldset className='grid gap-3 rounded-lg border p-4'>
          <legend className='px-1 text-sm font-medium'>Footer</legend>
          <p className='text-xs text-muted-foreground'>Shown at the bottom of every page (switch: Site → Big footer).</p>
          <div className='grid gap-2'>
            <Label>Links shown</Label>
            <div className='grid gap-2 sm:grid-cols-2'>
              {Object.entries(form.footer_link_options).map(([key, label]) => (
                <label key={key} className='flex items-center gap-2 text-sm'>
                  <Checkbox
                    checked={form.footer_links.includes(key)}
                    onCheckedChange={(v) => setForm({ ...form, footer_links: v ? [...form.footer_links, key] : form.footer_links.filter((k) => k !== key) })}
                  />
                  {label}
                </label>
              ))}
            </div>
            <p className='text-xs text-muted-foreground'>&quot;Contact us&quot; shows only when the help email above is filled in. Page texts: Website → Pages.</p>
          </div>
          <div className='grid gap-3 sm:grid-cols-2'>
            {Object.entries(form.footer_networks).map(([key, name]) => (
              <div key={key} className='grid gap-1.5'>
                <Label htmlFor={`social-${key}`}>{name} page</Label>
                <Input id={`social-${key}`} type='url' inputMode='url' value={form.footer_social[key] ?? ''} onChange={(e) => setForm({ ...form, footer_social: { ...form.footer_social, [key]: e.target.value } })} placeholder='https://… (empty = hidden)' />
              </div>
            ))}
          </div>
          <p className='text-xs text-muted-foreground'>Only https links on the network&apos;s own site are accepted (for example https://www.facebook.com/iibsom, https://t.me/iibsom, https://wa.me/252…).</p>
        </fieldset>
        <p className='text-sm text-muted-foreground'>
          Want the short step-by-step version?{' '}
          <Link to='/setup' className='text-primary underline'>
            Run the first-time setup again
          </Link>
        </p>
        <p className='text-sm text-muted-foreground'>Currency: {form.currency} (decision #3).</p>
        <Button type='submit' disabled={save.isPending}>
          Save settings
        </Button>
      </form>
    </ContentSection>
  )
}
