import { createFileRoute } from '@tanstack/react-router'
import { FeatureSwitches } from '@/features/features'

export const Route = createFileRoute('/_authenticated/features')({
  component: FeatureSwitches,
})
