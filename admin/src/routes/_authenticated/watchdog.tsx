import { z } from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { WatchdogPage } from '@/features/watchdog'

export const Route = createFileRoute('/_authenticated/watchdog')({
  component: WatchdogPage,
  validateSearch: z.object({
    tab: z.enum(['overview', 'history', 'settings']).catch('overview').default('overview'),
  }),
})
