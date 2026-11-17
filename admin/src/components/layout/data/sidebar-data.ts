import {
  Activity,
  BadgeCheck,
  BookOpen,
  FileText,
  FlaskConical,
  Flag,
  FolderTree,
  HeartPulse,
  Image,
  LayoutDashboard,
  ListChecks,
  MapPin,
  Megaphone,
  Palette,
  Store,
  Receipt,
  Settings,
  ShieldCheck,
  Star,
  ToggleRight,
  UserCog,
  Users,
} from 'lucide-react'
import { type StaffUser } from '@/stores/auth-store'
import { type SidebarData } from '../types'

/** Admin menu. Items are hidden for staff without the permission. */
export const sidebarData: SidebarData = {
  navGroups: [
    {
      title: 'General',
      items: [{ title: 'Dashboard', url: '/', icon: LayoutDashboard, permission: 'dashboard.view' }],
    },
    {
      title: 'Moderation',
      items: [
        { title: 'Ads', url: '/ads', icon: Megaphone, permission: 'ads.view' },
        { title: 'Waiting for approval', url: '/ads/pending', icon: ListChecks, permission: 'ads.moderate' },
        { title: 'Reports', url: '/reports', icon: Flag, permission: 'reports.view' },
        { title: 'Users', url: '/users', icon: Users, permission: 'users.view' },
        { title: 'ID checks', url: '/verifications', icon: BadgeCheck, permission: 'users.verify', feature: 'verified_badge' },
        { title: 'Ratings', url: '/reviews', icon: Star, permission: 'reviews.manage', feature: 'seller_ratings' },
        { title: 'Shops', url: '/shops', icon: Store, permission: 'shops.manage', feature: 'shops' },
        { title: 'Payments', url: '/payments', icon: Receipt, permission: 'payments.manage', feature: 'boosts' },
      ],
    },
    {
      title: 'Catalogue',
      items: [
        { title: 'Categories', url: '/categories', icon: FolderTree, permission: 'categories.manage' },
        { title: 'Cities and areas', url: '/locations', icon: MapPin, permission: 'locations.manage' },
      ],
    },
    {
      title: 'Website',
      items: [
        { title: 'Pages', url: '/pages', icon: FileText, permission: 'pages.manage' },
        { title: 'Home banners', url: '/banners', icon: Image, permission: 'banners.manage' },
        { title: 'Feature switches', url: '/features', icon: ToggleRight, permission: 'features.manage' },
      ],
    },
    {
      title: 'System',
      items: [
        { title: 'Staff', url: '/staff', icon: UserCog, permission: 'staff.manage' },
        { title: 'Activity log', url: '/activity', icon: Activity, permission: 'activity.view' },
        { title: 'System health', url: '/health', icon: HeartPulse, permission: 'health.view' },
        { title: 'Admin guide', url: '/guide', icon: BookOpen, feature: 'guide' },
        {
          title: 'Settings',
          icon: Settings,
          items: [
            { title: 'General', url: '/settings', icon: Settings, permission: 'settings.manage' },
            { title: 'Appearance', url: '/settings/appearance', icon: Palette },
            { title: 'Security (2FA)', url: '/settings/security', icon: ShieldCheck },
            { title: 'Demo data', url: '/settings/demo', icon: FlaskConical, permission: 'demo.manage' },
          ],
        },
      ],
    },
  ],
}

/** The menu for this staff member (items they may not use, or switched OFF, are removed). */
export function visibleNavGroups(can: (permission: string) => boolean, user: StaffUser | null): SidebarData['navGroups'] {
  const allowed = (item: { permission?: string; feature?: 'guide' | 'verified_badge' | 'seller_ratings' | 'shops' | 'boosts' }) => (!item.permission || can(item.permission)) && (!item.feature || !!user?.[item.feature])
  return sidebarData.navGroups
    .map((group) => ({
      ...group,
      items: group.items
        .map((item) => (item.items ? { ...item, items: item.items.filter(allowed) } : item))
        .filter((item) => allowed(item) && (!item.items || item.items.length > 0)),
    }))
    .filter((group) => group.items.length > 0)
}
