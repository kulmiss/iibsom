import { create } from 'zustand'
import { api } from '@/lib/api'

export type StaffUser = {
  id: number
  name: string
  email: string
  role: 'super_admin' | 'moderator' | 'support'
  role_label: string
  permissions: string[]
  brand_color: string
  demo_data: boolean
  /** "Admin guide" switch: "?" buttons and the Guide page. */
  guide: boolean
  /** "Verified badge" switch (Phase 2). */
  verified_badge: boolean
  /** "Seller ratings" switch (Phase 3). */
  seller_ratings: boolean
  /** "Business shop pages" switch (Phase 2). */
  shops: boolean
  /** "Paid boosts" switch (Phase 2). */
  boosts: boolean
  /** Super Admin who has not done the first-time setup yet. */
  setup_needed: boolean
}

interface AuthState {
  user: StaffUser | null
  loaded: boolean
  setUser: (user: StaffUser | null) => void
  /** Ask Laravel who is signed in. Returns null when not signed in. */
  load: () => Promise<StaffUser | null>
  signOut: () => Promise<void>
  can: (permission: string) => boolean
}

export const useAuthStore = create<AuthState>()((set, get) => ({
  user: null,
  loaded: false,
  setUser: (user) => {
    set({ user, loaded: true })
    if (user) applyBrandColor(user.brand_color)
  },
  load: async () => {
    try {
      // /session answers 200 with user = null when nobody is signed in (no error in the console).
      const { data } = await api.get<{ user: StaffUser | null }>('/session')
      get().setUser(data.user)
      return data.user
    } catch {
      set({ user: null, loaded: true })
      return null
    }
  },
  signOut: async () => {
    try {
      await api.post('/logout')
    } finally {
      set({ user: null, loaded: true })
    }
  },
  can: (permission) => !!get().user?.permissions.includes(permission),
}))

/** Settings → Appearance brand colour: becomes the admin accent colour. */
export function applyBrandColor(hex: string | undefined) {
  if (!hex || !/^#[0-9a-f]{6}$/i.test(hex)) return
  const root = document.documentElement.style
  root.setProperty('--primary', hex)
  root.setProperty('--primary-foreground', '#ffffff')
  root.setProperty('--ring', hex)
  root.setProperty('--sidebar-primary', hex)
  root.setProperty('--sidebar-primary-foreground', '#ffffff')
  root.setProperty('--chart-1', hex)
}
