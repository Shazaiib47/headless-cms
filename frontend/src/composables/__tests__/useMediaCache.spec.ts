import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { mediaApi } from '@/api/media'
import { clearMediaCache, getMedia, rememberMedia } from '@/composables/useMediaCache'
import type { MediaItem } from '@/types/api'

vi.mock('@/api/media', () => ({
  mediaApi: {
    get: vi.fn<(id: number) => Promise<{ data: MediaItem }>>(),
  },
}))

function item(id = 1): MediaItem {
  return {
    id,
    original_name: 'cat.jpg',
    alt_text: 'A cat',
    mime_type: 'image/jpeg',
    size: 1000,
    width: 100,
    height: 100,
    url: 'http://localhost:8000/storage/media/a.jpg',
    thumbnail_url: 'http://localhost:8000/storage/media/thumbs/a.jpg',
    uploaded_by: 1,
    created_at: '2026-10-19T10:00:00+00:00',
  }
}

describe('useMediaCache', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    clearMediaCache()
  })

  it('fetches an image once and then serves it from memory', async () => {
    vi.mocked(mediaApi.get).mockResolvedValue({ data: item(5) })

    const first = await getMedia(5)
    const second = await getMedia(5)

    expect(first).toEqual(item(5))
    expect(second).toEqual(item(5))
    expect(mediaApi.get).toHaveBeenCalledTimes(1)
    expect(mediaApi.get).toHaveBeenCalledWith(5)
  })

  it('shares one request between simultaneous callers', async () => {
    vi.mocked(mediaApi.get).mockResolvedValue({ data: item(5) })

    const [a, b] = await Promise.all([getMedia(5), getMedia(5)])

    expect(a).toEqual(b)
    expect(mediaApi.get).toHaveBeenCalledTimes(1)
  })

  it('serves images that were remembered without asking the server', async () => {
    rememberMedia(item(9))

    await expect(getMedia(9)).resolves.toEqual(item(9))
    expect(mediaApi.get).not.toHaveBeenCalled()
  })

  it('does not cache failures, so the next attempt asks again', async () => {
    vi.mocked(mediaApi.get)
      .mockRejectedValueOnce(new ApiError(500, 'Server exploded'))
      .mockResolvedValueOnce({ data: item(5) })

    await expect(getMedia(5)).rejects.toBeInstanceOf(ApiError)
    await expect(getMedia(5)).resolves.toEqual(item(5))
    expect(mediaApi.get).toHaveBeenCalledTimes(2)
  })
})
