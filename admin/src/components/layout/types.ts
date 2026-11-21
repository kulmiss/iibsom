import { type LinkProps } from '@tanstack/react-router'

type BaseNavItem = {
  title: string
  badge?: string
  icon?: React.ElementType
  /** Hidden unless the staff member has this permission. */
  permission?: string
  /** Hidden while this feature switch is OFF. */
  feature?: 'guide' | 'verified_badge' | 'seller_ratings' | 'shops' | 'boosts' | 'contact_form' | 'home_page' | 'watchdog' | 'scam_numbers'
}

type NavLink = BaseNavItem & {
  url: LinkProps['to'] | (string & {})
  items?: never
}

type NavCollapsible = BaseNavItem & {
  items: (BaseNavItem & { url: LinkProps['to'] | (string & {}) })[]
  url?: never
}

type NavItem = NavCollapsible | NavLink

type NavGroup = {
  title: string
  items: NavItem[]
}

type SidebarData = {
  navGroups: NavGroup[]
}

export type { SidebarData, NavGroup, NavItem, NavCollapsible, NavLink }
