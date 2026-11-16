import { CircleHelp } from 'lucide-react'
import { Link } from '@tanstack/react-router'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuthStore } from '@/stores/auth-store'
import { useGuide } from './use-guide'

/** "?" next to a page title: opens the part of the admin guide about this page. */
export function HelpButton({ section }: { section: string }) {
  const on = useAuthStore((s) => !!s.user?.guide)
  const { data, isError } = useGuide()
  if (!on || isError) return null
  const part = data?.sections.find((s) => s.slug === section)

  return (
    <Sheet>
      <SheetTrigger asChild>
        <Button variant='ghost' size='icon' className='size-8 rounded-full text-muted-foreground' aria-label='Help for this page'>
          <CircleHelp className='size-5' />
        </Button>
      </SheetTrigger>
      <SheetContent className='w-full overflow-y-auto sm:max-w-md'>
        <SheetHeader>
          <SheetTitle>{part?.title ?? 'Help'}</SheetTitle>
          <SheetDescription>From the admin guide.</SheetDescription>
        </SheetHeader>
        <div className='px-4 pb-6'>
          {data ? (
            // Made on the server from our own Markdown files, with HTML in them escaped.
            <div className='guide-content' dangerouslySetInnerHTML={{ __html: part?.html ?? '<p>No help for this page yet.</p>' }} />
          ) : (
            <Skeleton className='h-40 w-full' />
          )}
          <Link to='/guide' hash={section} className='mt-6 inline-block text-sm text-primary underline'>
            Open the whole guide
          </Link>
        </div>
      </SheetContent>
    </Sheet>
  )
}
