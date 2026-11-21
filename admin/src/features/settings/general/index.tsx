import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, Navigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
  confirm_whatsapp: string
  payments_test_mode: boolean
  verify_business_price: number
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
  return <GeneralForm key={JSON.stringify(data)} initial={data} />
}

function GeneralForm({ initial }: { initial: General }) {
  const qc = useQueryClient()
  const [form, setForm] = useState<General>(initial)
  const boostsOn = useAuthStore((st) => !!st.user?.boosts)
  const diasporaOn = useAuthStore((st) => !!st.user?.diaspora)
  const verifiedOn = useAuthStore((st) => !!st.user?.verified_badge)
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
    <div className='grid content-start gap-1.5' data-setting>
      <Label htmlFor={key}>{label}</Label>
      <Input id={key} type='number' className='max-w-32' value={form[key]} onChange={(e) => setForm({ ...form, [key]: Number(e.target.value) })} />
      <p className='text-xs text-muted-foreground'>{help}</p>
    </div>
  )
  const dirty = JSON.stringify(form) !== JSON.stringify(initial) || emails !== initial.weekly_emails.join('\n')
  const sections = [
    ['ads', 'Ads and posting'],
    ['languages', 'Languages'],
    ['words', 'Safety words'],
    ['emails', 'Emails'],
    ...(boostsOn ? [['payments', 'Payments']] : []),
    ...(verifiedOn ? [['verified', 'Verified badge']] : []),
    ...(diasporaOn ? [['abroad', 'Buyers abroad']] : []),
    ['footer', 'Footer'],
  ]

  return (
    <ContentSection title='Site rules' desc='Rules for ads, languages, safety words, emails and the footer. One Save button for the whole page.'>
      <form
        className='grid gap-4'
        onSubmit={(e) => {
          e.preventDefault()
          save.mutate()
        }}
      >
        <nav aria-label='Parts of this page' className='flex flex-wrap gap-1.5'>
          {sections.map(([id, label]) => (
            <a key={id} href={`#${id}`} className='rounded-full border px-3 py-1 text-xs font-medium hover:bg-accent'>
              {label}
            </a>
          ))}
        </nav>

        <Part id='ads' title='Ads and posting' desc='How long ads stay, and limits against spam.'>
          <div className='grid gap-5 sm:grid-cols-2'>
            {num('ads_expiry_days', 'Ads end after (days)', 'Sellers can renew an ad when it ends. 7 to 365.')}
            {num('ads_max_per_day', 'Ads per person per day', 'Stops spam. 1 to 100.')}
            {num('ads_max_photos', 'Photos per ad', '1 to 20.')}
            {num('reports_auto_hide_at', 'Hide an ad after this many reports', 'From different signed-in people (guests do not count). The ad waits for a staff check; nothing is deleted, nobody is banned. 2 to 50.')}
          </div>
          <p className='text-xs text-muted-foreground'>Currency: {form.currency} (decision #3).</p>
        </Part>

        <Part id='languages' title='Languages' desc='When 2 or more are ON, visitors see a language switch.'>
          <div className='grid gap-2' data-setting>
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
          </div>
        </Part>

        <Part id='words' title='Safety words' desc='Whole words, any case. Add Somali words too.'>
          <div className='grid gap-5 lg:grid-cols-2'>
            <div className='grid content-start gap-1.5' data-setting>
              <Label htmlFor='bad_words'>Bad words (one per line)</Label>
              <Textarea id='bad_words' rows={8} className='h-48 font-mono text-xs field-sizing-fixed' value={form.bad_words} onChange={(e) => setForm({ ...form, bad_words: e.target.value })} />
              <p className='text-xs text-muted-foreground'>Ads with these words are refused. Switch: Bad word filter.</p>
            </div>
            <div className='grid content-start gap-1.5' data-setting>
              <Label htmlFor='drug_words'>Drug words (one per line)</Label>
              <Textarea id='drug_words' rows={8} className='h-48 font-mono text-xs field-sizing-fixed' value={form.drug_words} onChange={(e) => setForm({ ...form, drug_words: e.target.value })} />
              <p className='text-xs text-muted-foreground'>
                New and edited ads with these words are refused (&quot;chat&quot; and &quot;Qatar&quot; are fine). Avoid normal words such as &quot;qaad&quot; (take) or &quot;jaad&quot; (kind). After changes, run <code>php artisan iibsom:drugs:check</code>.
              </p>
            </div>
          </div>
        </Part>

        <Part id='emails' title='Emails' desc='The Monday summary, and the email customers can write to.'>
          <div className='grid gap-5 lg:grid-cols-2'>
            <div className='grid content-start gap-1.5' data-setting>
              <Label htmlFor='weekly_emails'>Weekly summary to (up to 5, one per line)</Label>
              <Textarea id='weekly_emails' rows={3} className='text-sm' value={emails} onChange={(e) => setEmails(e.target.value)} placeholder='owner@iibsom.com' />
              <p className='text-xs text-muted-foreground'>
                Every Monday 07:00 (Mogadishu): last week&apos;s ads, users, reports and top categories. Empty = no email.
                {form.weekly_last_sent && ` Last sent: week ${form.weekly_last_sent}.`}
              </p>
              <div>
                <Button type='button' variant='outline' size='sm' disabled={sendTest.isPending} onClick={() => sendTest.mutate()}>
                  Send me a test now
                </Button>
              </div>
            </div>
            <div className='grid content-start gap-1.5' data-setting>
              <Label htmlFor='support_email'>Help email for customers</Label>
              <Input id='support_email' type='email' value={form.support_email} onChange={(e) => setForm({ ...form, support_email: e.target.value })} placeholder='help@iibsom.com' />
              <p className='text-xs text-muted-foreground'>Shown on the Help page and as &quot;Contact us&quot; in the footer. Empty = not shown.</p>
            </div>
            <div className='grid content-start gap-1.5' data-setting>
              <Label htmlFor='confirm_whatsapp'>iibsom WhatsApp number for “Confirm my number”</Label>
              <Input id='confirm_whatsapp' type='tel' value={form.confirm_whatsapp} onChange={(e) => setForm({ ...form, confirm_whatsapp: e.target.value })} placeholder='+252 61 000 0000' />
              <p className='text-xs text-muted-foreground'>Sellers send their code to this number; staff match it in Users and tick “Phone confirmed”. Empty = no WhatsApp button (staff call the seller instead).</p>
            </div>
          </div>
        </Part>

        {boostsOn && (
          <Part id='payments' title='Payments for boosts' desc='Merchant numbers sellers pay to.'>
            <label className='flex items-start justify-between gap-4 rounded-lg border p-3' data-setting>
              <span>
                <span className='block text-sm font-medium'>Test mode</span>
                <span className='block text-xs text-muted-foreground'>ON: sellers see &quot;Do not send real money&quot; and payments stay out of the real books. Turn OFF only when the numbers below are real.</span>
              </span>
              <Switch checked={form.payments_test_mode} onCheckedChange={(v) => setForm({ ...form, payments_test_mode: v })} aria-label='Test mode' />
            </label>
            <div className='grid gap-3 sm:grid-cols-2'>
              {Object.entries(form.payment_methods).map(([key, label]) => (
                <div key={key} className='grid gap-1.5' data-setting>
                  <Label htmlFor={`pay-${key}`}>{label} merchant number</Label>
                  <Input id={`pay-${key}`} inputMode='tel' value={form.payment_numbers[key] ?? ''} onChange={(e) => setForm({ ...form, payment_numbers: { ...form.payment_numbers, [key]: e.target.value } })} placeholder='Empty = not offered' />
                </div>
              ))}
            </div>
          </Part>
        )}

        {verifiedOn && (
          <Part id='verified' title='Verified badge' desc='People who are not a business always get the badge for free.'>
            <div className='grid content-start gap-1.5' data-setting='verify_business_price'>
              <Label htmlFor='verify_business_price'>Price for businesses (US dollars)</Label>
              <Input id='verify_business_price' type='number' min={0} max={1000} className='max-w-32' value={form.verify_business_price} onChange={(e) => setForm({ ...form, verify_business_price: Number(e.target.value) })} />
              <p className='text-xs text-muted-foreground'>Businesses = sellers with a shop page. 0 = free (recommended for launch). Nothing is paid on the site for this yet: staff tell the business how to pay.</p>
            </div>
          </Part>
        )}

        {diasporaOn && (
          <Part id='abroad' title='Prices for buyers abroad' desc='How much 1 US dollar is in each money (for example 0.92 for the euro). 0 or empty = not shown.'>
            {form.diaspora_rates_date && <p className='text-xs text-muted-foreground'>Last changed: {form.diaspora_rates_date}.</p>}
            <div className='grid gap-3 sm:grid-cols-2'>
              {Object.entries(form.diaspora_currencies).map(([code, name]) => (
                <div key={code} className='grid gap-1.5' data-setting>
                  <Label htmlFor={`rate-${code}`}>
                    {name} ({code})
                  </Label>
                  <Input id={`rate-${code}`} inputMode='decimal' value={form.diaspora_rates[code] ?? ''} onChange={(e) => setForm({ ...form, diaspora_rates: { ...form.diaspora_rates, [code]: e.target.value } })} placeholder='0 = not shown' />
                </div>
              ))}
            </div>
          </Part>
        )}

        <Part id='footer' title='Footer' desc='Shown at the bottom of every page (switch: Big footer).'>
          <div className='grid gap-2' data-setting>
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
            <p className='text-xs text-muted-foreground'>&quot;Contact us&quot; shows only when the help email is filled in. Page texts: Website → Pages.</p>
          </div>
          <div className='grid gap-3 sm:grid-cols-2'>
            {Object.entries(form.footer_networks).map(([key, name]) => (
              <div key={key} className='grid gap-1.5' data-setting>
                <Label htmlFor={`social-${key}`}>{name} page</Label>
                <Input id={`social-${key}`} type='url' inputMode='url' value={form.footer_social[key] ?? ''} onChange={(e) => setForm({ ...form, footer_social: { ...form.footer_social, [key]: e.target.value } })} placeholder='https://… (empty = hidden)' />
              </div>
            ))}
          </div>
          <p className='text-xs text-muted-foreground'>Only https links on the network&apos;s own site (for example https://www.facebook.com/iibsom, https://t.me/iibsom, https://wa.me/252…).</p>
        </Part>

        <p className='text-sm text-muted-foreground'>
          Want the short step-by-step version?{' '}
          <Link to='/setup' className='text-primary underline'>
            Run the first-time setup again
          </Link>
        </p>

        {/* Always reachable: sticks to the bottom while there are changes to save. */}
        <div className={cn('-mx-1 flex items-center justify-end gap-3 border-t bg-background/95 px-1 py-3 backdrop-blur', dirty && 'sticky bottom-0 z-10 border-primary/40')} data-save-bar>
          {dirty && <span className='me-auto text-sm font-medium'>You have changes that are not saved.</span>}
          {dirty && (
            <Button type='button' variant='ghost' onClick={() => {
                setForm(initial)
                setEmails(initial.weekly_emails.join('\n'))
              }}>
              Undo
            </Button>
          )}
          <Button type='submit' disabled={save.isPending || !dirty}>
            Save settings
          </Button>
        </div>
      </form>
    </ContentSection>
  )
}

/** One part of the page: a card with a title, a short line and an id to jump to. */
function Part({ id, title, desc, children }: { id: string; title: string; desc: string; children: React.ReactNode }) {
  return (
    <Card id={id} className='scroll-mt-24 gap-4 py-5'>
      <CardHeader className='px-5'>
        <CardTitle className='text-base'>{title}</CardTitle>
        <CardDescription>{desc}</CardDescription>
      </CardHeader>
      <CardContent className='grid gap-4 px-5'>{children}</CardContent>
    </Card>
  )
}
