import { http } from '@/api/http'
import { buildQuery } from '@/api/query'
import type { Paginated, PageDetail, PageInput, PageStatus, PageSummary } from '@/types/api'
import type { PreviewPage } from '@/types/render'

export interface PageListParams {
  search?: string
  status?: PageStatus | ''
  sort?: string
  page?: number
  per_page?: number
}

export const pagesApi = {
  list: (params: PageListParams = {}) =>
    http.get<Paginated<PageSummary>>(`/api/pages${buildQuery(params)}`),

  get: (id: number) => http.get<{ data: PageDetail }>(`/api/pages/${id}`),

  create: (input: PageInput) => http.post<{ data: PageDetail }>('/api/pages', input),

  update: (id: number, input: PageInput) =>
    http.patch<{ data: PageDetail }>(`/api/pages/${id}`, input),

  /** Pass the updated_at you loaded so the server can reject stale actions with 409. */
  publish: (id: number, updatedAt?: string) =>
    http.post<{ data: PageDetail }>(
      `/api/pages/${id}/publish`,
      updatedAt ? { updated_at: updatedAt } : undefined,
    ),

  unpublish: (id: number, updatedAt?: string) =>
    http.post<{ data: PageDetail }>(
      `/api/pages/${id}/unpublish`,
      updatedAt ? { updated_at: updatedAt } : undefined,
    ),

  preview: (id: number) => http.get<{ data: PreviewPage }>(`/api/pages/${id}/preview`),

  remove: (id: number) => http.delete<void>(`/api/pages/${id}`),
}
