import { mediaApi } from '@/api/media'
import type { MediaItem } from '@/types/api'

const cache = new Map<number, MediaItem>()
const pending = new Map<number, Promise<MediaItem>>()

/** Remembers an image the app has already seen (e.g. one just chosen in the picker). */
export function rememberMedia(item: MediaItem): void {
  cache.set(item.id, item)
}

export function clearMediaCache(): void {
  cache.clear()
  pending.clear()
}

/** Fetches an image by id once. Simultaneous callers share one request. Failures are not cached. */
export function getMedia(id: number): Promise<MediaItem> {
  const cached = cache.get(id)
  if (cached) return Promise.resolve(cached)

  const inFlight = pending.get(id)
  if (inFlight) return inFlight

  const request = mediaApi
    .get(id)
    .then(({ data }) => {
      cache.set(id, data)
      return data
    })
    .finally(() => {
      pending.delete(id)
    })

  pending.set(id, request)
  return request
}
