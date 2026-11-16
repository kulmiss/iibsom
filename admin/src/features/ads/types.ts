export type AdStatus = 'pending' | 'live' | 'rejected' | 'sold' | 'expired'

export type AdRow = {
  id: number
  title: string
  status: AdStatus
  status_label: string
  rejection_reason: string | null
  price: string
  category: string | null
  category_id: number
  city: string | null
  seller: string | null
  seller_id: number
  seller_banned: boolean
  thumb: string | null
  photos_count: number | null
  is_demo: boolean
  created_at: string
}

export type AdDetail = AdRow & {
  description: string
  condition: 'new' | 'used' | null
  area: string | null
  fields: { label: string; value: string }[]
  photos: { id: number; status: string; thumb: string | null; large: string | null }[]
  seller_info: { id: number; name: string; email: string; phone: string | null; city: string | null; banned: boolean; member_since: string; ads_total: number }
  reviewed_by: string | null
  reviewed_at: string | null
  published_at: string | null
  expires_at: string | null
  history: { summary: string; by: string | null; at: string }[]
}

export const STATUS_OPTIONS: { value: AdStatus; label: string }[] = [
  { value: 'pending', label: 'Waiting for approval' },
  { value: 'live', label: 'Live' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'sold', label: 'Sold' },
  { value: 'expired', label: 'Expired' },
]
