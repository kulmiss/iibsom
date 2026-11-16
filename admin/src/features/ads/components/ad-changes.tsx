import { type AdChange } from '../types'

/** Old → new for everything the seller changed since the last approval. */
export function AdChanges({ changes }: { changes: AdChange[] }) {
  return (
    <section aria-labelledby='ad-changes-title' className='rounded-md border border-violet-300 bg-violet-50/60 p-3 dark:border-violet-800 dark:bg-violet-950/40'>
      <h3 id='ad-changes-title' className='mb-2 text-sm font-semibold'>
        What the seller changed since approval
      </h3>
      {changes.length === 0 ? (
        <p className='text-sm text-muted-foreground'>Saved again without changes.</p>
      ) : (
        <dl className='space-y-3 text-sm'>
          {changes.map((c) => (
            <div key={c.key}>
              <dt className='font-medium'>{c.label}</dt>
              <dd className='mt-1 grid gap-1 sm:grid-cols-2'>
                <span className='rounded bg-red-50 px-2 py-1 break-words whitespace-pre-line text-red-900 line-through decoration-red-400/60 dark:bg-red-950 dark:text-red-200'>
                  <span className='sr-only'>Before: </span>
                  {c.before}
                </span>
                <span className='rounded bg-emerald-50 px-2 py-1 break-words whitespace-pre-line text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'>
                  <span className='sr-only'>Now: </span>
                  {c.after}
                </span>
              </dd>
            </div>
          ))}
        </dl>
      )}
    </section>
  )
}
