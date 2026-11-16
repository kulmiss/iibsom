import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/ads/pending')({
  component: () => <ComingSoon title='Waiting for approval' what='Approve or reject new ads, one by one or many at once.' />,
})
