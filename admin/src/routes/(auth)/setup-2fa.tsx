import { z } from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { TwoFactorSetup } from '@/features/auth/two-factor-setup'

export const Route = createFileRoute('/(auth)/setup-2fa')({
  component: TwoFactorSetup,
  validateSearch: z.object({ redirect: z.string().optional() }),
})
