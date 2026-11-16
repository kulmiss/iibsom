import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/ads/')({
  component: () => <ComingSoon title='Ads' what='All ads with search, filters, edit and delete.' />,
})
