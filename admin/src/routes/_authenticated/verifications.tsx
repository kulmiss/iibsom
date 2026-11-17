import { createFileRoute } from '@tanstack/react-router'
import { Verifications } from '@/features/verifications'

export const Route = createFileRoute('/_authenticated/verifications')({
  component: Verifications,
})
