import { http } from '@/api/http'
import { buildQuery } from '@/api/query'
import type { Paginated, PageDetail, PageInput, PageStatus, PageSummary } from '@/types/api'

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

  remove: (id: number) => http.delete<void>(`/api/pages/${id}`),
}
