import type { Block } from '@/types/blocks'

export type Role = 'admin' | 'editor' | 'viewer'

export const ROLES: readonly Role[] = ['admin', 'editor', 'viewer']

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

/** A user as returned by the admin user-management API. */
export interface ManagedUser extends User {
  created_at: string
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

/** A full page. `content` is validated and converted by fromApiBlocks(). */
export interface PageDetail extends PageSummary {
  content: unknown
  meta_title: string | null
  meta_description: string | null
  og_image_id: number | null
  created_by: number | null
  updated_by: number | null
}

export interface PageInput {
  title: string
  slug: string
  meta_title: string
  meta_description: string
  content: Block[]
  /** The timestamp the editor loaded; the server answers 409 if it is stale. */
  updated_at?: string
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
