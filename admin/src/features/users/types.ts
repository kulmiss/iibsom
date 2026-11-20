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
  /** "Phone confirmed" (PR 108): who ticked it and when. */
  phone_confirmed: { at: string; by: string | null } | null
  /** A "confirm my number" code the seller asked for, to match their WhatsApp message. */
  phone_code: { code: string; at: string } | null
}

export type UserDetail = UserRow & {
  ads_by_status: Record<string, number>
  reports_made: number
  phone_blocked: boolean
}
