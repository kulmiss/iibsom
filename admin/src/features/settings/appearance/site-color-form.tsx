import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type Info = { color: string; readable: boolean; contrast: number; suggestion: string | null; palette: Record<string, string>; default?: string; enabled?: boolean }

// Colours that keep text easy to read (checked on the server too).
const PRESETS = ['#1d6bca', '#0f766e', '#0f7a3d', '#7c3aed', '#b91c1c', '#c2410c', '#be185d', '#334155']

/** Website colour (PR 88): one colour for the visitor site's top bar, buttons and links. */
export function SiteColorForm() {
  const canEdit = useAuthStore((s) => s.can('settings.manage'))
  const qc = useQueryClient()
  const saved = useQuery({ queryKey: ['site-color'], queryFn: async () => (await api.get<Info>('/settings/site-color')).data, enabled: canEdit, retry: false })
  const [color, setColor] = useState<string | null>(null)
  const [info, setInfo] = useState<Info | null>(null)

  const current = color ?? saved.data?.color ?? '#1d6bca'

  // Check the colour on the server (same rule as saving), a moment after it stops changing.
  useEffect(() => {
    if (!canEdit || !saved.data) return
    const t = setTimeout(async () => {
      try {
        setInfo((await api.post<Info>('/settings/site-color/check', { color: current })).data)
      } catch {
        /* keep the last answer */
      }
    }, 250)
    return () => clearTimeout(t)
  }, [current, canEdit, saved.data])

  const save = useMutation({
    mutationFn: async (value: string) => (await api.put<Info>('/settings/site-color', { color: value })).data,
    onSuccess: () => {
      toast.success('Website colour saved. Visitors see it now.')
      setColor(null)
      qc.invalidateQueries({ queryKey: ['site-color'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  if (!canEdit || saved.isError) return null
  if (!saved.data) return null

  const palette = info?.palette ?? saved.data.palette
  const ok = info ? info.readable : saved.data.readable
  const changed = current !== saved.data.color

  return (
    <div className='space-y-3' data-site-color-form>
      <div>
        <Label htmlFor='site-color'>Website colour</Label>
        <p className='text-sm text-muted-foreground'>
          The colour of the top bar, buttons and links on the website. Pick one colour; the lighter and darker shades are made from it. Dark mode follows too.
        </p>
      </div>
      <div className='flex flex-wrap items-center gap-2'>
        {PRESETS.map((p) => (
          <button
            key={p}
            type='button'
            onClick={() => setColor(p)}
            className='size-8 rounded-full ring-offset-2 ring-offset-background data-[active=true]:ring-2 data-[active=true]:ring-foreground'
            style={{ background: p }}
            data-active={current === p}
            aria-label={`Use colour ${p}`}
          />
        ))}
        <Input id='site-color' type='color' value={current} onChange={(e) => setColor(e.target.value.toLowerCase())} className='h-9 w-14 p-1' />
        <span className='font-mono text-sm'>{current}</span>
      </div>

      {/* Preview: how the website will look */}
      <div className='overflow-hidden rounded-lg border' aria-label='Preview'>
        <div className='flex items-center justify-between px-3 py-2 text-sm font-semibold text-white' style={{ background: current }}>
          <span>iibsom</span>
          <span className='rounded-md px-2 py-0.5 text-xs' style={{ background: palette['800'] }}>Search</span>
        </div>
        <div className='flex flex-wrap items-center gap-3 p-3 text-sm' style={{ background: '#ffffff', color: '#1c1917' }}>
          <span className='rounded-md px-3 py-1.5 font-semibold text-white' style={{ background: current }}>Show phone number</span>
          <span className='rounded-md px-3 py-1.5 font-medium' style={{ background: palette['50'], color: current }}>Cars</span>
          <span className='font-medium underline' style={{ color: current }}>See all ads</span>
        </div>
        <div className='flex'>
          {Object.entries(palette).map(([step, hex]) => (
            <span key={step} className='h-4 flex-1' style={{ background: hex }} title={`${step}: ${hex}`} />
          ))}
        </div>
      </div>

      {info && !info.readable && (
        <p className='text-sm text-destructive' role='alert'>
          Too light: text on it would be hard to read (contrast {info.contrast}:1, needs 4.5:1).
          {info.suggestion && (
            <>
              {' '}
              <button type='button' className='font-medium underline' onClick={() => setColor(info.suggestion!)}>
                Use {info.suggestion} instead
              </button>
            </>
          )}
        </p>
      )}
      <div className='flex flex-wrap gap-2'>
        <Button type='button' onClick={() => save.mutate(current)} disabled={save.isPending || !ok || !changed}>
          Save website colour
        </Button>
        {saved.data.default && current !== saved.data.default && (
          <Button type='button' variant='outline' onClick={() => setColor(saved.data!.default!)}>
            Back to iibsom blue
          </Button>
        )}
      </div>
    </div>
  )
}
