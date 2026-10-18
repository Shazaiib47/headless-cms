import { http } from '@/api/http'
import type { Paginated, PageStatus, PageSummary } from '@/types/api'

export interface PageListParams {
  search?: string
  status?: PageStatus | ''
  sort?: string
  page?: number
  per_page?: number
}

export const pagesApi = {
  list(params: PageListParams = {}) {
    const query = new URLSearchParams()

    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== '') query.set(key, String(value))
    }

    const qs = query.toString()
    return http.get<Paginated<PageSummary>>(`/api/pages${qs ? `?${qs}` : ''}`)
  },

  remove: (id: number) => http.delete<void>(`/api/pages/${id}`),
}
