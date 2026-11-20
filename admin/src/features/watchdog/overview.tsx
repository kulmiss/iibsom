import { useState } from 'react'
import { Link } from '@tanstack/react-router'
import { CircleAlert, CircleCheck, CircleX } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { AdSheet } from '@/features/ads/components/ad-sheet'
import { ProblemRow, When } from './problem-row'
import { type Overview as Data, type Status } from './types'

const BIG: Record<Status, { icon: typeof CircleCheck; text: string; tone: string }> = {
  ok: {
    icon: CircleCheck,
    text: 'All good',
    tone: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
  },
  warning: {
    icon: CircleAlert,
    text: 'Warnings',
    tone: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
  },
  urgent: {
    icon: CircleX,
    text: 'Problems',
    tone: 'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300',
  },
}
const DOT: Record<Status, string> = {
  ok: 'bg-emerald-500',
  warning: 'bg-amber-500',
  urgent: 'bg-red-600',
}

export function Overview({ data }: { data: Data }) {
  const [openAd, setOpenAd] = useState<number | null>(null)
  const big = BIG[data.status]
  const Icon = big.icon
  const noChannel = !data.channels.email && !data.channels.telegram

  return (
    <div className='grid gap-4'>
      <div className={cn('flex items-center gap-3 rounded-xl border p-4 sm:p-5', big.tone)} role='status' data-watchdog-status={data.status}>
        <Icon className='size-8 shrink-0' />
        <div>
          <p className='text-xl font-semibold'>
            {data.status === 'ok' ? '✅' : data.status === 'warning' ? '🟠' : '🔴'} {big.text}
          </p>
          <p className='text-sm opacity-90'>
            {data.last_run ? (
              <>
                Last check <When iso={data.last_run} />.
              </>
            ) : (
              'The checks have not run yet. They start with the cron (every 5 minutes).'
            )}
          </p>
        </div>
      </div>

      {noChannel && (
        <p className='rounded-lg border border-dashed p-3 text-sm'>
          No alerts can reach you yet. Add your email or a Telegram bot in the{' '}
          <Link to='/watchdog' search={{ tab: 'settings' }} className='font-medium underline underline-offset-4'>
            Settings
          </Link>{' '}
          tab.
        </p>
      )}

      <div className='grid grid-cols-2 gap-3 lg:grid-cols-4'>
        {data.cards.map((c) => (
          <Card key={c.area} className='gap-2 py-4' data-card={c.area}>
            <CardHeader className='px-4'>
              <CardTitle className='flex items-center gap-2 text-sm'>
                <span className={cn('size-2.5 shrink-0 rounded-full', DOT[c.status])} aria-hidden />
                {c.label}
              </CardTitle>
            </CardHeader>
            <CardContent className='px-4 text-sm'>
              <p className='font-medium'>{c.urgent + c.warning === 0 ? (c.info > 0 ? `All good · ${c.info} note${c.info > 1 ? 's' : ''}` : 'All good') : [c.urgent && `${c.urgent} urgent`, c.warning && `${c.warning} warning`].filter(Boolean).join(', ')}</p>
              <p className='text-xs text-muted-foreground'>
                {c.last_check ? (
                  <>
                    Checked <When iso={c.last_check} />
                  </>
                ) : (
                  'Not checked yet'
                )}
              </p>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle className='text-base'>Open problems ({data.problems.length})</CardTitle>
        </CardHeader>
        <CardContent className='divide-y'>
          {data.problems.length === 0 && <p className='text-sm text-muted-foreground'>Nothing open. 🎉</p>}
          {data.problems.map((p) => (
            <ProblemRow key={p.id} problem={p} onOpenAd={setOpenAd} />
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className='text-base'>Please check ({data.please_check.length})</CardTitle>
          <p className='text-sm text-muted-foreground'>Ads that look odd (for example a very low price). Nothing was hidden or banned. Open, decide, then mark as fixed.</p>
        </CardHeader>
        <CardContent className='divide-y'>
          {data.please_check.length === 0 && <p className='text-sm text-muted-foreground'>Nothing to check.</p>}
          {data.please_check.map((p) => (
            <ProblemRow key={p.id} problem={p} onOpenAd={setOpenAd} />
          ))}
        </CardContent>
      </Card>

      <AdSheet adId={openAd} onOpenChange={(o) => !o && setOpenAd(null)} />
    </div>
  )
}
