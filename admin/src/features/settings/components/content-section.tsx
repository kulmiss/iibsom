type ContentSectionProps = {
  title: string
  desc: string
  children: React.JSX.Element
}

/** The title of a settings page and its content. The whole page scrolls (no box with its own scroll, PR 101). */
export function ContentSection({ title, desc, children }: ContentSectionProps) {
  return (
    <div className='flex min-w-0 flex-1 flex-col gap-4'>
      <div>
        <h2 className='text-xl font-semibold tracking-tight'>{title}</h2>
        <p className='text-sm text-muted-foreground'>{desc}</p>
      </div>
      <div className='min-w-0 pb-16'>{children}</div>
    </div>
  )
}
