import { DynamicIcon, type IconName } from 'lucide-react/dynamic'

/** Same Lucide icon names as the public site (resources/svg/icons). Loaded on demand. */
export function CategoryIcon({ name, className = 'size-4' }: { name: string; className?: string }) {
  return <DynamicIcon name={name as IconName} className={className} fallback={() => <span className={className} />} />
}
