import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'

/** Approve / reject / delete / bulk, then refresh every ads list and the dashboard. */
export function useAdActions() {
  const qc = useQueryClient()
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['ads'] })
    qc.invalidateQueries({ queryKey: ['ad'] })
    qc.invalidateQueries({ queryKey: ['dashboard'] })
  }
  const onError = (e: unknown) => toast.error(apiErrorMessage(e))

  return {
    approve: useMutation({
      mutationFn: (id: number) => api.post(`/ads/${id}/approve`),
      onSuccess: () => {
        toast.success('Ad approved, it is live now.')
        refresh()
      },
      onError,
    }),
    reject: useMutation({
      mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/ads/${id}/reject`, { reason }),
      onSuccess: () => {
        toast.success('Ad rejected. The seller sees your reason.')
        refresh()
      },
      onError,
    }),
    remove: useMutation({
      mutationFn: (id: number) => api.delete(`/ads/${id}`),
      onSuccess: () => {
        toast.success('Ad deleted.')
        refresh()
      },
      onError,
    }),
    bulk: useMutation({
      mutationFn: (body: { ids: number[]; action: 'approve' | 'reject' | 'delete'; reason?: string }) =>
        api.post<{ done: number; skipped: number }>('/ads/bulk', body).then((r) => r.data),
      onSuccess: (r) => {
        toast.success(`${r.done} done${r.skipped ? `, ${r.skipped} skipped (status did not allow it)` : ''}.`)
        refresh()
      },
      onError,
    }),
    update: useMutation({
      mutationFn: ({ id, ...body }: { id: number; title: string; description: string; price: number | null; price_negotiable: boolean }) => api.put(`/ads/${id}`, body),
      onSuccess: () => {
        toast.success('Ad saved.')
        refresh()
      },
      onError,
    }),
  }
}

export const REJECT_PRESETS = ['Photos are not clear or not real', 'Wrong category', 'Price is missing or not real', 'Not allowed on iibsom', 'Drugs are not allowed', 'Duplicate ad', 'Contact details in the text']
