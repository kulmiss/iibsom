import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { format, formatDistanceToNow } from 'date-fns'
import { BellOff, Check, ExternalLink } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { LEVEL_ICON, LEVEL_LABEL, type Problem } from './types'

const MUTE_HOURS = [1, 4, 12, 24, 72, 168]

export function When({ iso }: { iso: string }) {
  return (
    <time dateTime={iso} title={format(new Date(iso), 'd MMM yyyy HH:mm')}>
      {formatDistanceToNow(new Date(iso), { addSuffix: true })}
    </time>
  )
}

/** One problem: level, title, what happened, steps, times, and Mark as fixed / Mute (open ones only). */
export function ProblemRow({ problem: p, onOpenAd, actions = true }: { problem: Problem; onOpenAd?: (id: number) => void; actions?: boolean }) {
  const qc = useQueryClient()
  const done = (text: string) => {
    toast.success(text)
    qc.invalidateQueries({ queryKey: ['watchdog'] })
  }
  const fix = useMutation({
    mutationFn: () => api.post(`/watchdog/problems/${p.id}/fix`),
    onSuccess: () => done('Marked as fixed. If the check still finds it, it opens again.'),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const mute = useMutation({
    mutationFn: (hours: number) => api.post(`/watchdog/problems/${p.id}/mute`, { hours }),
    onSuccess: (_, hours) => done(`No reminders for ${hours} hour(s).`),
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const open = actions && !p.fixed_at

  return (
    <div className='flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-start' data-problem={p.key}>
      <div className='min-w-0 flex-1'>
        <p className='font-medium'>
          <span aria-hidden>{LEVEL_ICON[p.level]}</span> <span className='sr-only'>{LEVEL_LABEL[p.level]}: </span>
          {p.title}
          {p.muted_until && (
            <Badge variant='outline' className='ms-2 align-middle'>
              <BellOff className='size-3' /> muted
            </Badge>
          )}
        </p>
        <p className='mt-1 text-sm break-words text-muted-foreground'>{p.message}</p>
        {open && p.steps.length > 0 && (
          <ol className='mt-2 list-decimal space-y-0.5 ps-5 text-sm'>
            {p.steps.map((s) => (
              <li key={s}>{s}</li>
            ))}
          </ol>
        )}
        <p className='mt-2 text-xs text-muted-foreground'>
          First seen <When iso={p.first_seen_at} />
          {p.count > 1 && (
            <>
              {' '}
              · seen {p.count} times · last <When iso={p.last_seen_at} />
            </>
          )}
          {p.fixed_at && p.fixed_how !== 'event' && (
            <>
              {' '}
              · {p.fixed_how === 'staff' ? `marked fixed by ${p.fixed_by ?? 'staff'}` : p.fixed_how === 'off' ? 'check turned off' : 'back to normal'} <When iso={p.fixed_at} />
            </>
          )}
          {p.muted_until && (
            <>
              {' '}
              · muted until {format(new Date(p.muted_until), 'd MMM HH:mm')}
              {p.muted_by && ` by ${p.muted_by}`}
            </>
          )}
        </p>
        {p.subject_id && (
          <div className='mt-2 flex flex-wrap gap-2'>
            {p.subject_type === 'ad' && onOpenAd && (
              <Button size='sm' variant='outline' onClick={() => onOpenAd(p.subject_id!)}>
                <ExternalLink /> Open ad #{p.subject_id}
              </Button>
            )}
            {p.subject_type === 'user' && (
              <Button size='sm' variant='outline' asChild>
                <Link to='/users' search={{ open: p.subject_id }}>
                  <ExternalLink /> Open user #{p.subject_id}
                </Link>
              </Button>
            )}
          </div>
        )}
      </div>
      {open && (
        <div className='flex shrink-0 gap-2'>
          <Button size='sm' variant='outline' onClick={() => fix.mutate()} disabled={fix.isPending}>
            <Check /> Mark as fixed
          </Button>
          {p.level !== 'info' && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button size='sm' variant='ghost' disabled={mute.isPending} aria-label={`Mute “${p.title}”`}>
                  <BellOff /> Mute
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align='end'>
                <DropdownMenuLabel>No reminders for…</DropdownMenuLabel>
                {MUTE_HOURS.map((h) => (
                  <DropdownMenuItem key={h} onSelect={() => mute.mutate(h)}>
                    {h < 24 ? `${h} hour${h > 1 ? 's' : ''}` : `${h / 24} day${h > 24 ? 's' : ''}`}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </div>
      )}
    </div>
  )
}
