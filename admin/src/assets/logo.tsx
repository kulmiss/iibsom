import { type SVGProps } from 'react'
import { cn } from '@/lib/utils'

/** iibsom mark: rounded square in the brand colour with "ii". */
export function Logo({ className, ...props }: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox='0 0 32 32' xmlns='http://www.w3.org/2000/svg' className={cn('size-6', className)} {...props}>
      <title>iibsom</title>
      <rect width='32' height='32' rx='8' fill='var(--primary)' />
      <text x='16' y='22' fontFamily='system-ui,sans-serif' fontSize='16' fontWeight='800' fill='var(--primary-foreground)' textAnchor='middle'>
        ii
      </text>
    </svg>
  )
}
