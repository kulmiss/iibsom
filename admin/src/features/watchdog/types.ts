export type Level = 'urgent' | 'warning' | 'info'
export type Status = 'ok' | 'warning' | 'urgent'

export type Problem = {
  id: number
  key: string
  area: string
  level: Level
  title: string
  message: string
  steps: string[]
  count: number
  first_seen_at: string
  last_seen_at: string
  fixed_at: string | null
  fixed_how: 'auto' | 'staff' | 'off' | 'event' | null
  fixed_by: string | null
  muted_until: string | null
  muted_by: string | null
  subject_type: 'ad' | 'user' | null
  subject_id: number | null
}

export type Card = {
  area: string
  label: string
  status: Status
  urgent: number
  warning: number
  info: number
  last_check: string | null
}

export type Overview = {
  status: Status
  last_run: string | null
  cards: Card[]
  problems: Problem[]
  please_check: Problem[]
  channels: { email: boolean; telegram: boolean }
}

export type CheckSetting = {
  key: string
  area: string
  label: string
  help: string
  every: string
  level: Level
  unit: string | null
  on: boolean
  limit: number | null
}

export type Options = {
  email_on: boolean
  email_to: string
  telegram_on: boolean
  telegram_chat_id: string
  quiet_on: boolean
  quiet_from: string
  quiet_to: string
  digest_time: string
  reminder_hours: number
  heartbeat_url: string
}

export type SettingsData = {
  options: Options
  telegram_token_set: boolean
  checks: CheckSetting[]
  areas: Record<string, string>
  backup_path_set: boolean
  mail_set: boolean
}

export const LEVEL_ICON: Record<Level, string> = {
  urgent: '🔴',
  warning: '🟠',
  info: '🟢',
}
export const LEVEL_LABEL: Record<Level, string> = {
  urgent: 'Urgent',
  warning: 'Warning',
  info: 'Info',
}
