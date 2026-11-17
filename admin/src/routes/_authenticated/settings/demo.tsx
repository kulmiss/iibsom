import { createFileRoute } from '@tanstack/react-router'
import { SettingsDemo } from '@/features/settings/demo'

export const Route = createFileRoute('/_authenticated/settings/demo')({
  component: SettingsDemo,
})
