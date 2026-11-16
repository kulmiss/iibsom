export type CategoryRow = {
  id: number
  parent_id: number | null
  name_en: string
  name_so: string | null
  slug: string
  icon: string
  enabled: boolean
  popular: boolean
  has_condition: boolean
  ads_count: number
  fields_count: number
  children?: CategoryRow[]
}

export type FieldRow = {
  id: number
  key: string
  type: 'text' | 'number' | 'select' | 'boolean'
  label_en: string
  label_so: string | null
  options: string[]
  unit: string | null
  required: boolean
  filterable: boolean
  enabled: boolean
}
