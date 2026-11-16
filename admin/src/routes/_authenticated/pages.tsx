import { createFileRoute } from '@tanstack/react-router'
import { Pages } from '@/features/pages'

export const Route = createFileRoute('/_authenticated/pages')({
  component: Pages,
})
