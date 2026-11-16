import { createFileRoute } from '@tanstack/react-router'
import { PendingAds } from '@/features/ads'

export const Route = createFileRoute('/_authenticated/ads/pending')({
  component: PendingAds,
})
