import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Mail, Save, Send } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { LEVEL_ICON, type CheckSetting, type Options, type SettingsData } from './types'

type Form = Options & {
  telegram_token: string
  remove_telegram_token: boolean
  checks: Record<string, { on: boolean; limit: number | null }>
}

function toForm(d: SettingsData): Form {
  return {
    ...d.options,
    telegram_token: '',
    remove_telegram_token: false,
    checks: Object.fromEntries(d.checks.map((c) => [c.key, { on: c.on, limit: c.limit }])),
  }
}

function Field({ id, label, help, error, children }: { id: string; label: string; help?: string; error?: string; children: React.ReactNode }) {
  return (
    <div className='grid gap-1.5'>
      <Label htmlFor={id}>{label}</Label>
      {children}
      {help && <p className='text-xs text-muted-foreground'>{help}</p>}
      {error && <p className='text-xs text-destructive'>{error}</p>}
    </div>
  )
}

function Toggle({ id, label, checked, onChange, help }: { id: string; label: string; checked: boolean; onChange: (v: boolean) => void; help?: string }) {
  return (
    <div className='flex items-start justify-between gap-3'>
      <div>
        <Label htmlFor={id}>{label}</Label>
        {help && <p className='text-xs text-muted-foreground'>{help}</p>}
      </div>
      <Switch id={id} checked={checked} onCheckedChange={onChange} />
    </div>
  )
}

export function Settings({ data }: { data: SettingsData }) {
  const qc = useQueryClient()
  const [form, setForm] = useState<Form>(() => toForm(data))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const set = <K extends keyof Form>(key: K, value: Form[K]) => setForm((f) => ({ ...f, [key]: value }))
  const setCheck = (key: string, value: Partial<{ on: boolean; limit: number | null }>) =>
    setForm((f) => ({
      ...f,
      checks: { ...f.checks, [key]: { ...f.checks[key], ...value } },
    }))

  const save = useMutation({
    mutationFn: () =>
      api.put<SettingsData>('/watchdog/settings', {
        ...form,
        telegram_token: form.telegram_token || null,
      }),
    onSuccess: (res) => {
      setErrors({})
      qc.setQueryData(['watchdog', 'settings'], res.data)
      qc.invalidateQueries({ queryKey: ['watchdog'] })
      toast.success('Watchdog settings saved.')
    },
    onError: (e) => {
      setErrors(apiFieldErrors(e))
      toast.error(apiErrorMessage(e))
    },
  })
  const test = useMutation({
    mutationFn: async (channel: 'email' | 'telegram') => (await api.post<{ sent: boolean; message: string }>('/watchdog/test', { channel })).data,
    onSuccess: (r) => (r.sent ? toast.success(r.message) : toast.error(r.message)),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const byArea = (area: string) => data.checks.filter((c) => c.area === area)

  return (
    <form
      className='grid gap-4'
      onSubmit={(e) => {
        e.preventDefault()
        save.mutate()
      }}
    >
      <Card>
        <CardHeader>
          <CardTitle className='text-base'>Where alerts go</CardTitle>
          <CardDescription>Save first, then send a test. Alerts never contain passwords, tokens or people's details.</CardDescription>
        </CardHeader>
        <CardContent className='grid gap-6 md:grid-cols-2'>
          <div className='grid content-start gap-4'>
            <Toggle
              id='email_on'
              label='Email'
              checked={form.email_on}
              onChange={(v) => set('email_on', v)}
              help={data.mail_set ? undefined : 'Mail is not set up on this server yet (MAIL_MAILER in .env).'}
            />
            <Field id='email_to' label='Your email address' error={errors.email_to}>
              <Input id='email_to' type='email' value={form.email_to} onChange={(e) => set('email_to', e.target.value)} placeholder='you@example.com' autoComplete='email' />
            </Field>
            <Button type='button' variant='outline' className='justify-self-start' onClick={() => test.mutate('email')} disabled={test.isPending}>
              <Mail /> Send test email
            </Button>
          </div>
          <div className='grid content-start gap-4'>
            <Toggle id='telegram_on' label='Telegram' checked={form.telegram_on} onChange={(v) => set('telegram_on', v)} />
            <Field
              id='telegram_token'
              label='Bot token'
              help={data.telegram_token_set ? 'A token is saved (it is never shown). Type a new one only to change it.' : 'From @BotFather in Telegram. Kept encrypted.'}
              error={errors.telegram_token}
            >
              <Input
                id='telegram_token'
                type='password'
                value={form.telegram_token}
                onChange={(e) => set('telegram_token', e.target.value)}
                placeholder={data.telegram_token_set ? '•••••••• saved' : '123456789:AA…'}
                autoComplete='off'
              />
            </Field>
            {data.telegram_token_set && (
              <label className='flex items-center gap-2 text-sm'>
                <input type='checkbox' checked={form.remove_telegram_token} onChange={(e) => set('remove_telegram_token', e.target.checked)} /> Remove the saved token
              </label>
            )}
            <Field id='telegram_chat_id' label='Chat ID' help='Your chat with the bot, or a group (starts with -). See the guide.' error={errors.telegram_chat_id}>
              <Input id='telegram_chat_id' value={form.telegram_chat_id} onChange={(e) => set('telegram_chat_id', e.target.value)} placeholder='123456789' inputMode='numeric' autoComplete='off' />
            </Field>
            <Button type='button' variant='outline' className='justify-self-start' onClick={() => test.mutate('telegram')} disabled={test.isPending}>
              <Send /> Send test Telegram
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className='text-base'>When</CardTitle>
          <CardDescription>Times are Mogadishu time.</CardDescription>
        </CardHeader>
        <CardContent className='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>
          <Field id='digest_time' label='Daily digest at' help='One short message a day with the numbers and open problems.' error={errors.digest_time}>
            <Input id='digest_time' type='time' value={form.digest_time} onChange={(e) => set('digest_time', e.target.value)} />
          </Field>
          <Field id='reminder_hours' label='Remind every (hours)' help='Urgent problems that are not fixed are sent again.' error={errors.reminder_hours}>
            <Input id='reminder_hours' type='number' min={1} max={72} value={form.reminder_hours} onChange={(e) => set('reminder_hours', Number(e.target.value))} />
          </Field>
          <div className='grid content-start gap-3 sm:col-span-2'>
            <Toggle id='quiet_on' label='Quiet hours' checked={form.quiet_on} onChange={(v) => set('quiet_on', v)} help='Warnings wait until the morning. Urgent alerts still come.' />
            <div className='grid grid-cols-2 gap-3'>
              <Field id='quiet_from' label='From' error={errors.quiet_from}>
                <Input id='quiet_from' type='time' value={form.quiet_from} onChange={(e) => set('quiet_from', e.target.value)} disabled={!form.quiet_on} />
              </Field>
              <Field id='quiet_to' label='To' error={errors.quiet_to}>
                <Input id='quiet_to' type='time' value={form.quiet_to} onChange={(e) => set('quiet_to', e.target.value)} disabled={!form.quiet_on} />
              </Field>
            </div>
          </div>
          <div className='sm:col-span-2 lg:col-span-4'>
            <Field
              id='heartbeat_url'
              label='Heartbeat URL (optional)'
              help='From healthchecks.io: the site pings it every 5 minutes; if the pings stop, healthchecks.io tells you the cron or the server is down.'
              error={errors.heartbeat_url}
            >
              <Input id='heartbeat_url' type='url' value={form.heartbeat_url} onChange={(e) => set('heartbeat_url', e.target.value)} placeholder='https://hc-ping.com/…' autoComplete='off' />
            </Field>
          </div>
        </CardContent>
      </Card>

      {Object.entries(data.areas).map(([area, label]) => (
        <Card key={area}>
          <CardHeader>
            <CardTitle className='text-base'>Checks: {label}</CardTitle>
          </CardHeader>
          <CardContent className='divide-y'>
            {byArea(area).map((c: CheckSetting) => (
              <div key={c.key} className='flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start' data-check={c.key}>
                <div className='min-w-0 flex-1'>
                  <Label htmlFor={`on-${c.key}`} className='font-medium'>
                    <span aria-hidden>{LEVEL_ICON[c.level]}</span> {c.label}
                  </Label>
                  <p className='text-xs text-muted-foreground'>
                    {c.help} <span className='whitespace-nowrap'>({c.every})</span>
                  </p>
                  {c.key === 'health.backups' && !data.backup_path_set && <p className='mt-1 text-xs text-amber-700 dark:text-amber-400'>Not set up: add WATCHDOG_BACKUP_PATH to .env.</p>}
                </div>
                <div className='flex shrink-0 items-center gap-3'>
                  {c.limit !== null && (
                    <div className='flex items-center gap-2'>
                      <Input
                        type='number'
                        min={0}
                        className='h-8 w-24'
                        value={form.checks[c.key]?.limit ?? 0}
                        onChange={(e) =>
                          setCheck(c.key, {
                            limit: Number(e.target.value),
                          })
                        }
                        aria-label={`Limit for “${c.label}”`}
                        disabled={!form.checks[c.key]?.on}
                      />
                      <span className='max-w-32 text-xs text-muted-foreground'>{c.unit}</span>
                    </div>
                  )}
                  <Switch id={`on-${c.key}`} checked={!!form.checks[c.key]?.on} onCheckedChange={(v) => setCheck(c.key, { on: v })} />
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      ))}

      <div className='sticky bottom-0 -mx-1 flex justify-end bg-background/90 px-1 py-3 backdrop-blur'>
        <Button type='submit' disabled={save.isPending}>
          <Save /> Save settings
        </Button>
      </div>
    </form>
  )
}
