export type UserRow = {
  id: number
  name: string
  email: string
  phone: string | null
  whatsapp: boolean
  city: string | null
  ads_count: number | null
  profile_complete: boolean
  banned: boolean
  ban_reason: string | null
  flag: string | null
  created_at: string
  last_login_at: string | null
  is_demo: boolean
  /** Has the Verified badge (only while the switch is ON). */
  verified: boolean
}

export type UserDetail = UserRow & {
  ads_by_status: Record<string, number>
  reports_made: number
  phone_blocked: boolean
}
