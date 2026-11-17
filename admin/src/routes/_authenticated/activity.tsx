import { createFileRoute } from '@tanstack/react-router'
import { ActivityLog } from '@/features/activity'

export const Route = createFileRoute('/_authenticated/activity')({
  component: ActivityLog,
})
