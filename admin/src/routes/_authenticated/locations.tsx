import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/locations')({
  component: () => <ComingSoon title='Cities and areas' what='Turn cities and areas on and off, add new ones.' />,
})
