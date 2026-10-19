import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { ApiError } from '@/api/http'
import { mediaApi } from '@/api/media'
import { MAX_UPLOAD_BYTES, useMediaUploads } from '@/composables/useMediaUploads'
import type { MediaItem } from '@/types/api'

vi.mock('@/api/media', () => ({
  mediaApi: {
    upload: vi.fn<(file: File, altText?: string) => Promise<{ data: MediaItem }>>(),
  },
}))

function media(id = 1): MediaItem {
  return {
    id,
    original_name: 'cat.jpg',
    alt_text: null,
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

function file(name = 'a.jpg'): File {
  return new File(['x'], name, { type: 'image/jpeg' })
}

describe('useMediaUploads', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('uploads one file at a time, in order', async () => {
    const resolvers: Array<() => void> = []
    vi.mocked(mediaApi.upload).mockImplementation(
      () =>
        new Promise((resolve) => {
          resolvers.push(() => resolve({ data: media() }))
        }),
    )
    const uploads = useMediaUploads(vi.fn<(item: MediaItem) => void>())

    uploads.add([file('a.jpg'), file('b.jpg')])
    await flushPromises()

    expect(mediaApi.upload).toHaveBeenCalledTimes(1)
    expect(uploads.items.value.map((item) => item.status)).toEqual(['uploading', 'queued'])
    expect(uploads.busy.value).toBe(true)

    resolvers[0]?.()
    await flushPromises()

    expect(mediaApi.upload).toHaveBeenCalledTimes(2)
    expect(uploads.items.value.map((item) => item.status)).toEqual(['done', 'uploading'])

    resolvers[1]?.()
    await flushPromises()

    expect(uploads.items.value.map((item) => item.status)).toEqual(['done', 'done'])
  })

  it('reports each success and clears the busy flag', async () => {
    vi.mocked(mediaApi.upload).mockResolvedValue({ data: media(7) })
    const onUploaded = vi.fn<(item: MediaItem) => void>()
    const uploads = useMediaUploads(onUploaded)

    uploads.add([file()])
    await flushPromises()

    expect(onUploaded).toHaveBeenCalledWith(media(7))
    expect(uploads.busy.value).toBe(false)
  })

  it('turns server errors into readable messages', async () => {
    const cases: Array<[ApiError, string]> = [
      [
        new ApiError(422, 'Invalid.', { file: ['The file must be a JPEG, PNG or WebP image.'] }),
        'The file must be a JPEG, PNG or WebP image.',
      ],
      [new ApiError(413, 'Payload Too Large'), 'The file is too large.'],
      [new ApiError(429, 'Too Many Attempts.'), 'Too many uploads. Wait a minute and try again.'],
      [new ApiError(403, 'Forbidden'), 'You do not have permission to upload images.'],
      [new ApiError(0, 'Could not reach the server.'), 'Could not reach the server.'],
    ]

    for (const [error, expected] of cases) {
      vi.mocked(mediaApi.upload).mockRejectedValueOnce(error)
      const uploads = useMediaUploads(vi.fn<(item: MediaItem) => void>())

      uploads.add([file()])
      await flushPromises()

      expect(uploads.items.value[0]?.status).toBe('error')
      expect(uploads.items.value[0]?.message).toBe(expected)
    }
  })

  it('skips files over the size limit without sending a request', async () => {
    const big = file('big.jpg')
    Object.defineProperty(big, 'size', { value: MAX_UPLOAD_BYTES + 1 })
    const uploads = useMediaUploads(vi.fn<(item: MediaItem) => void>())

    uploads.add([big])
    await flushPromises()

    expect(mediaApi.upload).not.toHaveBeenCalled()
    expect(uploads.items.value[0]?.status).toBe('error')
    expect(uploads.items.value[0]?.message).toContain('5 MB')
  })

  it('keeps going after a failed upload', async () => {
    vi.mocked(mediaApi.upload)
      .mockRejectedValueOnce(new ApiError(422, 'Invalid.', { file: ['Not an image.'] }))
      .mockResolvedValueOnce({ data: media(2) })
    const onUploaded = vi.fn<(item: MediaItem) => void>()
    const uploads = useMediaUploads(onUploaded)

    uploads.add([file('bad.jpg'), file('good.jpg')])
    await flushPromises()

    expect(uploads.items.value.map((item) => item.status)).toEqual(['error', 'done'])
    expect(onUploaded).toHaveBeenCalledTimes(1)
  })

  it('clears finished items but keeps active ones', async () => {
    const resolvers: Array<() => void> = []
    vi.mocked(mediaApi.upload).mockImplementation(
      () =>
        new Promise((resolve) => {
          resolvers.push(() => resolve({ data: media() }))
        }),
    )
    const uploads = useMediaUploads(vi.fn<(item: MediaItem) => void>())

    uploads.add([file('a.jpg')])
    resolvers[0]?.()
    await flushPromises()

    uploads.add([file('b.jpg')])
    await flushPromises()
    uploads.clearFinished()

    expect(uploads.items.value.map((item) => item.name)).toEqual(['b.jpg'])
  })
})
