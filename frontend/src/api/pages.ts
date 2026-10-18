import { http } from '@/api/http'
import { buildQuery } from '@/api/query'
import type { Paginated, PageStatus, PageSummary } from '@/types/api'

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

  remove: (id: number) => http.delete<void>(`/api/pages/${id}`),
}
