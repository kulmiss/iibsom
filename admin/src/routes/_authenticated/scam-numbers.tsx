import { createFileRoute } from '@tanstack/react-router'
import { ScamNumbers } from '@/features/scam-numbers'

export const Route = createFileRoute('/_authenticated/scam-numbers')({
  component: ScamNumbers,
})
