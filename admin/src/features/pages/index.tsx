import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ExternalLink } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { Page } from '@/components/layout/page'

type SitePage = { id: number; slug: string; url: string; enabled: boolean; title_en: string; title_so: string | null; body_en: string; body_so: string | null; updated_at: string | null }

export function Pages() {
  const { data, isLoading } = useQuery({ queryKey: ['pages'], queryFn: async () => (await api.get<SitePage[]>('/pages')).data })
  const [selected, setSelected] = useState<number | null>(null)
  const current = data?.find((p) => p.id === (selected ?? data[0]?.id))

  return (
    <Page title='Pages' description='About, Safety tips, Terms and Privacy. Write in Markdown: **bold**, ## heading, - list.'>
      {isLoading && <Skeleton className='h-96' />}
      <div className='grid gap-4 lg:grid-cols-[14rem_1fr]'>
        <div className='flex gap-2 overflow-x-auto lg:flex-col'>
          {data?.map((p) => (
            <Button key={p.id} variant={current?.id === p.id ? 'secondary' : 'ghost'} className='justify-start' onClick={() => setSelected(p.id)}>
              {p.title_en} {!p.enabled && <Badge variant='outline'>OFF</Badge>}
            </Button>
          ))}
        </div>
        {current && <PageEditor key={current.id} page={current} />}
      </div>
    </Page>
  )
}

function PageEditor({ page }: { page: SitePage }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({ title_en: page.title_en, title_so: page.title_so ?? '', body_en: page.body_en, body_so: page.body_so ?? '', enabled: page.enabled })
  const [preview, setPreview] = useState<string | null>(null)

  const save = useMutation({
    mutationFn: () => api.put(`/pages/${page.id}`, form),
    onSuccess: () => {
      toast.success('Page saved. It is live on the site now.')
      qc.invalidateQueries({ queryKey: ['pages'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const showPreview = async (body: string) => setPreview((await api.post<{ html: string }>('/pages/preview', { body })).data.html)

  return (
    <Card>
      <CardContent className='space-y-4'>
        <div className='flex flex-wrap items-center justify-between gap-2'>
          <a href={page.url} target='_blank' rel='noreferrer' className='inline-flex items-center gap-1 text-sm text-primary underline'>
            {page.url} <ExternalLink className='size-3' />
          </a>
          <label className='flex items-center gap-2 text-sm'>
            ON <Switch checked={form.enabled} onCheckedChange={(v) => setForm({ ...form, enabled: v })} />
          </label>
        </div>
        <Tabs defaultValue='en'>
          <TabsList>
            <TabsTrigger value='en'>English</TabsTrigger>
            <TabsTrigger value='so'>Somali</TabsTrigger>
          </TabsList>
          {(['en', 'so'] as const).map((lang) => (
            <TabsContent key={lang} value={lang} className='space-y-3'>
              <div className='grid gap-2'>
                <Label htmlFor={`title-${lang}`}>Title</Label>
                <Input id={`title-${lang}`} value={form[`title_${lang}`]} onChange={(e) => setForm({ ...form, [`title_${lang}`]: e.target.value })} placeholder={lang === 'so' ? 'Empty = English is shown' : ''} />
              </div>
              <div className='grid gap-2'>
                <Label htmlFor={`body-${lang}`}>Text</Label>
                <Textarea id={`body-${lang}`} rows={16} className='font-mono text-sm' value={form[`body_${lang}`]} onChange={(e) => setForm({ ...form, [`body_${lang}`]: e.target.value })} />
              </div>
              <Button type='button' variant='outline' size='sm' onClick={() => showPreview(form[`body_${lang}`])}>
                Preview
              </Button>
            </TabsContent>
          ))}
        </Tabs>
        {preview !== null && (
          // Safe: the server escapes any HTML typed into the text (Markdown only).
          <div className='prose-preview rounded-md border bg-muted/30 p-4 text-sm [&_a]:text-primary [&_a]:underline [&_h2]:mt-4 [&_h2]:font-semibold [&_li]:ms-5 [&_li]:list-disc [&_p]:my-2' dangerouslySetInnerHTML={{ __html: preview }} />
        )}
        <Button onClick={() => save.mutate()} disabled={save.isPending || !form.title_en || !form.body_en}>
          Save page
        </Button>
      </CardContent>
    </Card>
  )
}
