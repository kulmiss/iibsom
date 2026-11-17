import { Download, Printer } from 'lucide-react'
import { Page } from '@/components/layout/page'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useGuide } from './use-guide'

export function GuidePage() {
  const { data, isError } = useGuide()

  return (
    <Page
      title='Admin guide'
      description='How every page of the admin works, in easy words. Tap ? next to a page title to open its part.'
      actions={
        <>
          <Button variant='outline' asChild>
            <a href='/admin/api/guide/print' target='_blank' rel='noopener'>
              <Printer /> Print or save as PDF
            </a>
          </Button>
          {data?.pdf && (
            <Button asChild>
              <a href='/admin/api/guide/pdf'>
                <Download /> Download PDF
              </a>
            </Button>
          )}
        </>
      }
    >
      {isError && <p className='text-destructive'>The guide could not be loaded. Try again in a moment.</p>}
      {!data && !isError && <Skeleton className='h-96 w-full' />}
      {data && (
        <div className='grid gap-4 lg:grid-cols-[14rem_1fr]'>
          <nav aria-label='Guide contents' className='lg:sticky lg:top-20 lg:self-start'>
            <ol className='grid gap-1 text-sm'>
              {data.sections.map((s) => (
                <li key={s.slug}>
                  <a href={`#${s.slug}`} className='text-muted-foreground hover:text-foreground hover:underline'>
                    {s.title}
                  </a>
                </li>
              ))}
            </ol>
          </nav>
          <div className='grid gap-4'>
            {data.sections.map((s) => (
              <Card key={s.slug} id={s.slug} className='scroll-mt-20'>
                <CardHeader>
                  <CardTitle>{s.title}</CardTitle>
                </CardHeader>
                <CardContent>
                  {/* Made on the server from our own Markdown files, with HTML in them escaped. */}
                  <div className='guide-content' dangerouslySetInnerHTML={{ __html: s.html }} />
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      )}
    </Page>
  )
}
