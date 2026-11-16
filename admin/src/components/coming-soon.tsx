import { Telescope } from 'lucide-react'
import { Page } from '@/components/layout/page'

/** Placeholder for admin screens that arrive in the next PRs. */
export function ComingSoon({ title = 'Coming soon', what }: { title?: string; what?: string }) {
  return (
    <Page title={title}>
      <div className='flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-10 text-center'>
        <Telescope size={56} className='text-muted-foreground' />
        <h2 className='text-xl font-semibold'>This screen is being built</h2>
        <p className='max-w-md text-muted-foreground'>{what ?? 'It arrives in one of the next updates.'}</p>
      </div>
    </Page>
  )
}
