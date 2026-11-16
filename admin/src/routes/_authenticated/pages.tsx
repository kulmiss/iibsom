import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/pages')({
  component: () => <ComingSoon title='Pages' what='Edit About, Terms, Privacy and Safety tips.' />,
})
