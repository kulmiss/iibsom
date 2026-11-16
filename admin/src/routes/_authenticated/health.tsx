import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/health')({
  component: () => <ComingSoon title='System health' what='Cron, queue, storage, cache, mail and image checks for this server.' />,
})
