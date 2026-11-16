import { createFileRoute, redirect } from '@tanstack/react-router'
import { useAuthStore } from '@/stores/auth-store'
import { AuthenticatedLayout } from '@/components/layout/authenticated-layout'

export const Route = createFileRoute('/_authenticated')({
  // Every admin page: must be signed in (password + 2FA). Otherwise → sign in.
  beforeLoad: async ({ location }) => {
    const state = useAuthStore.getState()
    const user = state.user ?? (await state.load())
    if (!user) {
      throw redirect({ to: '/sign-in', search: { redirect: location.href } })
    }
  },
  component: AuthenticatedLayout,
})
