import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, Lock } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Page } from '@/components/layout/page'

type Need = { key: string; label: string; enabled: boolean }
type Section = {
  position: number
  area: string
  fixed: boolean
  key: string | null
  label: string
  description: string
  note: string | null
  enabled: boolean
  default: boolean
  waiting_for: Need[]
}
type Data = { sections: Section[]; home_url: string }

/** Admin → Home page (PR 86): every part of the customer home page, top to bottom, with its own on/off. */
export function HomePage() {
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['home-page'], queryFn: async () => (await api.get<Data>('/home-page')).data })

  const toggle = useMutation({
    // The normal feature-switch endpoint: activity log and page cache work as for every switch.
    mutationFn: (s: Section) => api.put(`/features/${s.key}`, { enabled: !s.enabled }),
    onSuccess: (_, s) => {
      toast.success(`“${s.label}” is ${s.enabled ? 'hidden' : 'shown'} on the home page now.`)
      qc.invalidateQueries({ queryKey: ['home-page'] })
      qc.invalidateQueries({ queryKey: ['features'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const areas = [...new Set((data?.sections ?? []).map((s) => s.area))]

  return (
    <Page
      title='Home page'
      help='home-page'
      description='Every part of the home page, top to bottom. Turn a part off to hide it for everyone. Nothing is deleted; turn it on again any time.'
      actions={
        data && (
          <Button variant='outline' asChild>
            <a href={data.home_url} target='_blank' rel='noopener'>
              <ExternalLink /> Open home page
            </a>
          </Button>
        )
      }
    >
      {isLoading && <Skeleton className='h-96' />}
      <div className='grid max-w-3xl gap-4'>
        {areas.map((area) => (
          <Card key={area}>
            <CardHeader>
              <CardTitle className='text-base'>{area}</CardTitle>
            </CardHeader>
            <CardContent className='divide-y'>
              {data!.sections
                .filter((s) => s.area === area)
                .map((s) => (
                  <div key={s.position} className='flex items-start gap-3 py-3 first:pt-0 last:pb-0' data-section={s.key ?? 'fixed'}>
                    <span className='mt-0.5 w-6 shrink-0 text-sm tabular-nums text-muted-foreground'>{s.position}.</span>
                    <div className='min-w-0 flex-1'>
                      <p className='font-medium'>
                        {s.label} {!s.fixed && s.enabled !== s.default && <Badge variant='outline'>changed</Badge>}
                      </p>
                      <p className='text-sm text-muted-foreground'>{s.description}</p>
                      {s.note && <p className='mt-1 text-sm text-muted-foreground'>{s.note}</p>}
                      {s.enabled && s.waiting_for.length > 0 && (
                        <p className='mt-1 text-sm text-amber-700 dark:text-amber-400'>
                          Not shown yet: also turn on {s.waiting_for.map((n) => `“${n.label}”`).join(' and ')} in Feature switches.
                        </p>
                      )}
                    </div>
                    {s.fixed ? (
                      <span className='flex shrink-0 items-center gap-1 text-xs text-muted-foreground' title='Always shown: this is how people find ads.'>
                        <Lock className='size-3.5' /> Always shown
                      </span>
                    ) : (
                      <Switch
                        checked={s.enabled}
                        disabled={toggle.isPending}
                        onCheckedChange={() => toggle.mutate(s)}
                        aria-label={`Show “${s.label}” on the home page`}
                      />
                    )}
                  </div>
                ))}
            </CardContent>
          </Card>
        ))}
      </div>
    </Page>
  )
}
