export type Role = 'admin' | 'editor' | 'viewer'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export type PageStatus = 'draft' | 'published'

export interface PageSummary {
  id: number
  title: string
  slug: string
  status: PageStatus
  published_at: string | null
  created_at: string
  updated_at: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}
