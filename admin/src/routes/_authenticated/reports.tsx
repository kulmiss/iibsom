import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/reports')({
  component: () => <ComingSoon title='Reports' what='Ads people reported: scam, wrong category, sold, offensive.' />,
})
