import { createFileRoute } from '@tanstack/react-router'
import { AllAds } from '@/features/ads'

export const Route = createFileRoute('/_authenticated/ads/')({
  component: AllAds,
})
