import { http } from '@/api/http'
import { buildQuery } from '@/api/query'
import type { MediaItem, Paginated } from '@/types/api'

export interface MediaListParams {
  search?: string
  page?: number
  per_page?: number
}

export const mediaApi = {
  list: (params: MediaListParams = {}) =>
    http.get<Paginated<MediaItem>>(`/api/media${buildQuery(params)}`),

  get: (id: number) => http.get<{ data: MediaItem }>(`/api/media/${id}`),

  upload(file: File, altText?: string) {
    const form = new FormData()
    form.append('file', file)
    if (altText) form.append('alt_text', altText)

    return http.post<{ data: MediaItem }>('/api/media', form)
  },

  update: (id: number, altText: string | null) =>
    http.patch<{ data: MediaItem }>(`/api/media/${id}`, { alt_text: altText }),

  remove: (id: number) => http.delete<void>(`/api/media/${id}`),
}
