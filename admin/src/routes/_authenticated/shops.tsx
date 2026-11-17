import { createFileRoute } from '@tanstack/react-router'
import { Shops } from '@/features/shops'

export const Route = createFileRoute('/_authenticated/shops')({
  component: Shops,
})
