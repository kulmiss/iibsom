import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'

export type GuideSection = { slug: string; title: string; html: string }
export type Guide = { sections: GuideSection[]; pdf: boolean }

/** The admin guide (loaded once per visit). Nothing is loaded while the switch is OFF. */
export function useGuide() {
  const on = useAuthStore((s) => !!s.user?.guide)
  return useQuery({
    queryKey: ['guide'],
    queryFn: async () => (await api.get<Guide>('/guide')).data,
    enabled: on,
    staleTime: Infinity,
  })
}
