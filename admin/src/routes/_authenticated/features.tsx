import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/features')({
  component: () => <ComingSoon title='Feature switches' what='Turn every feature on or off, with a preview of what changes.' />,
})
