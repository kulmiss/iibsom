import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/categories')({
  component: () => <ComingSoon title='Categories' what='Add and edit categories and their fields without code.' />,
})
