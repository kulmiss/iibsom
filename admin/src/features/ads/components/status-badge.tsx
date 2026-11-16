import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { type AdStatus } from '../types'

const STYLES: Record<AdStatus, string> = {
  pending: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300',
  live: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
  rejected: 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300',
  sold: 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300',
  expired: 'border-stone-300 bg-stone-50 text-stone-700 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-300',
}

export function StatusBadge({ status, label }: { status: AdStatus; label: string }) {
  return (
    <Badge variant='outline' className={cn('whitespace-nowrap', STYLES[status])}>
      {label}
    </Badge>
  )
}

/** The seller changed the ad after it was approved. */
export function EditedBadge() {
  return (
    <Badge
      variant='outline'
      title='Changed by the seller after approval'
      className='ms-1 whitespace-nowrap border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-800 dark:bg-violet-950 dark:text-violet-300'
    >
      Edited
    </Badge>
  )
}
