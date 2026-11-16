import { createFileRoute } from '@tanstack/react-router'
import { ComingSoon } from '@/components/coming-soon'

export const Route = createFileRoute('/_authenticated/banners')({
  component: () => <ComingSoon title='Home banners' what='Banners on the home page.' />,
})
