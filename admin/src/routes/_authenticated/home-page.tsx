import { createFileRoute } from '@tanstack/react-router'
import { HomePage } from '@/features/home-page'

export const Route = createFileRoute('/_authenticated/home-page')({
  component: HomePage,
})
