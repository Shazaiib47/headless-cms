import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import MediaUploader from '../MediaUploader.vue'
import { ApiError } from '@/api/http'
import { mediaApi } from '@/api/media'
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
    thumbnail_url: null,
    uploaded_by: 1,
    created_at: '2026-10-19T10:00:00+00:00',
  }
}

function file(name: string): File {
  return new File(['x'], name, { type: 'image/jpeg' })
}

async function choose(wrapper: ReturnType<typeof mount>, files: File[]) {
  const input = wrapper.find('input[type="file"]')
  Object.defineProperty(input.element, 'files', { value: files, configurable: true })
  await input.trigger('change')
  await flushPromises()
}

describe('MediaUploader', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('uploads the chosen files and shows their status', async () => {
    vi.mocked(mediaApi.upload).mockResolvedValue({ data: media() })
    const wrapper = mount(MediaUploader)

    await choose(wrapper, [file('a.jpg'), file('b.jpg')])

    expect(mediaApi.upload).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('a.jpg')
    expect(wrapper.text()).toContain('b.jpg')
    expect(wrapper.text()).toContain('2 uploaded')
    expect(wrapper.emitted('uploaded')).toHaveLength(2)
  })

  it('reports once when the whole queue has finished', async () => {
    vi.mocked(mediaApi.upload).mockResolvedValue({ data: media() })
    const wrapper = mount(MediaUploader)

    await choose(wrapper, [file('a.jpg'), file('b.jpg')])

    expect(wrapper.emitted('finished')).toHaveLength(1)
  })

  it('accepts dropped files', async () => {
    vi.mocked(mediaApi.upload).mockResolvedValue({ data: media() })
    const wrapper = mount(MediaUploader)

    await wrapper.find('[data-test="dropzone"]').trigger('drop', {
      dataTransfer: { files: [file('dropped.jpg')] },
    })
    await flushPromises()

    expect(mediaApi.upload).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('dropped.jpg')
  })

  it('shows the server message for a failed upload and lets the user dismiss it', async () => {
    vi.mocked(mediaApi.upload).mockRejectedValue(
      new ApiError(422, 'Invalid.', { file: ['The file must be a JPEG, PNG or WebP image.'] }),
    )
    const wrapper = mount(MediaUploader)

    await choose(wrapper, [file('notes.jpg')])

    expect(wrapper.text()).toContain('The file must be a JPEG, PNG or WebP image.')
    expect(wrapper.text()).toContain('0 uploaded, 1 failed')

    await wrapper.find('[data-test="dismiss"]').trigger('click')

    expect(wrapper.text()).not.toContain('notes.jpg')
  })
})
