import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/staff')({
  component: () => <ComingSoon title='Staff' what='Add Moderators and Support, change roles, reset 2FA.' />,
})
