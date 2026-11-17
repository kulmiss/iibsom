import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { applyBrandColor, useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const PRESETS = ['#1f6fd1', '#0f766e', '#16a34a', '#ea580c', '#dc2626', '#7c3aed', '#db2777', '#0f172a']

/** Brand colour = the admin accent colour (buttons, links, charts). Saved for all staff. */
export function BrandColorForm() {
  const user = useAuthStore((s) => s.user)
  const setUser = useAuthStore((s) => s.setUser)
  const canEdit = useAuthStore((s) => s.can('settings.manage'))
  const [color, setColor] = useState(user?.brand_color ?? '#1f6fd1')

  const save = useMutation({
    mutationFn: async (value: string) => (await api.put<{ brand_color: string }>('/settings/appearance', { brand_color: value })).data,
    onSuccess: (data) => {
      if (user) setUser({ ...user, brand_color: data.brand_color })
      toast.success('Brand colour saved for all staff.')
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const preview = (value: string) => {
    setColor(value)
    applyBrandColor(value)
  }

  return (
    <div className='space-y-3'>
      <div>
        <Label htmlFor='brand-color'>Brand colour</Label>
        <p className='text-sm text-muted-foreground'>Changes the accent colour of the admin for everyone.{!canEdit && ' Only the Super Admin can change it.'}</p>
      </div>
      <div className='flex flex-wrap items-center gap-2'>
        {PRESETS.map((p) => (
          <button
            key={p}
            type='button'
            disabled={!canEdit}
            onClick={() => preview(p)}
            className='size-8 rounded-full ring-offset-2 ring-offset-background disabled:opacity-50 data-[active=true]:ring-2 data-[active=true]:ring-foreground'
            style={{ background: p }}
            data-active={color.toLowerCase() === p}
            aria-label={`Use colour ${p}`}
          />
        ))}
        <Input id='brand-color' type='color' value={color} disabled={!canEdit} onChange={(e) => preview(e.target.value)} className='h-9 w-14 p-1' />
        <span className='font-mono text-sm'>{color}</span>
      </div>
      {canEdit && (
        <Button type='button' onClick={() => save.mutate(color)} disabled={save.isPending}>
          Save brand colour
        </Button>
      )}
    </div>
  )
}
