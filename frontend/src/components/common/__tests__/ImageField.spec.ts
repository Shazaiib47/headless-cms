import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ImageField from '../ImageField.vue'
import { ApiError } from '@/api/http'
import { mediaApi, type MediaListParams } from '@/api/media'
import { clearMediaCache } from '@/composables/useMediaCache'
import { useAuthStore } from '@/stores/auth'
import type { MediaItem, Paginated } from '@/types/api'

vi.mock('@/api/media', () => ({
  mediaApi: {
    get: vi.fn<(id: number) => Promise<{ data: MediaItem }>>(),
    list: vi.fn<(params?: MediaListParams) => Promise<Paginated<MediaItem>>>(),
    upload: vi.fn<(file: File, altText?: string) => Promise<{ data: MediaItem }>>(),
  },
}))

function item(overrides: Partial<MediaItem> = {}): MediaItem {
  return {
    id: 1,
    original_name: 'cat.jpg',
    alt_text: 'A cat',
    mime_type: 'image/jpeg',
    size: 37991,
    width: 447,
    height: 447,
    url: 'http://localhost:8000/storage/media/a.jpg',
    thumbnail_url: 'http://localhost:8000/storage/media/thumbs/a.jpg',
    uploaded_by: 2,
    created_at: '2026-10-19T10:00:00+00:00',
    ...overrides,
  }
}

function page(items: MediaItem[]): Paginated<MediaItem> {
  return {
    data: items,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: items.length,
      from: items.length ? 1 : null,
      to: items.length || null,
    },
  }
}

function mountField(props: { imageId: number | null; errors?: string[] }) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore(pinia).user = { id: 1, name: 'Tester', email: 'tester@example.com', role: 'editor' }

  return mount(ImageField, {
    props: { id: 'hero-1-image', label: 'Hero image', ...props },
    global: { plugins: [pinia] },
  })
}

describe('ImageField', () => {
  beforeAll(() => {
    // jsdom does not implement the modal dialog methods.
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) {
      this.setAttribute('open', '')
    }
  })

  beforeEach(() => {
    vi.resetAllMocks()
    clearMediaCache()
    vi.mocked(mediaApi.list).mockResolvedValue(
      page([item(), item({ id: 2, original_name: 'dog.jpg' })]),
    )
  })

  it('offers to choose an image when none is selected', () => {
    const wrapper = mountField({ imageId: null })

    expect(wrapper.text()).toContain('No image selected.')
    expect(wrapper.find('[data-test="choose"]').text()).toContain('Choose image for Hero image')
    expect(wrapper.find('[data-test="remove"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="preview"]').exists()).toBe(false)
    expect(mediaApi.get).not.toHaveBeenCalled()
  })

  it("shows the selected image's thumbnail and file name", async () => {
    vi.mocked(mediaApi.get).mockResolvedValue({ data: item({ id: 5 }) })
    const wrapper = mountField({ imageId: 5 })

    expect(wrapper.text()).toContain('Loading image…')
    await flushPromises()

    expect(wrapper.find('[data-test="preview"]').attributes('src')).toBe(
      'http://localhost:8000/storage/media/thumbs/a.jpg',
    )
    expect(wrapper.find('[data-test="status"]').text()).toBe('cat.jpg')
    expect(wrapper.find('[data-test="choose"]').text()).toContain('Change image')
    expect(wrapper.find('[data-test="remove"]').exists()).toBe(true)
  })

  it('says so when the image is no longer in the library', async () => {
    vi.mocked(mediaApi.get).mockRejectedValue(new ApiError(404, 'Not found.'))
    const wrapper = mountField({ imageId: 5 })
    await flushPromises()

    expect(wrapper.find('[data-test="status"]').text()).toContain('no longer in the media library')
    expect(wrapper.find('[data-test="preview"]').exists()).toBe(false)
  })

  it('says so when the preview could not be loaded', async () => {
    vi.mocked(mediaApi.get).mockRejectedValue(new ApiError(500, 'Server exploded'))
    const wrapper = mountField({ imageId: 5 })
    await flushPromises()

    expect(wrapper.find('[data-test="status"]').text()).toContain('Could not load a preview')
  })

  it('opens the picker, selects an image and closes', async () => {
    const wrapper = mountField({ imageId: null })

    await wrapper.find('[data-test="choose"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('dialog').exists()).toBe(true)

    await wrapper.find('[data-test="pick"]').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('update:imageId')).toEqual([[1]])
    expect(wrapper.find('dialog').exists()).toBe(false)
  })

  it('removes the selected image', async () => {
    vi.mocked(mediaApi.get).mockResolvedValue({ data: item({ id: 5 }) })
    const wrapper = mountField({ imageId: 5 })
    await flushPromises()

    await wrapper.find('[data-test="remove"]').trigger('click')

    expect(wrapper.emitted('update:imageId')).toEqual([[null]])
  })

  it('shows server errors for the field', () => {
    const wrapper = mountField({
      imageId: null,
      errors: ['This image block needs an image before the page can be published.'],
    })

    expect(wrapper.find('[data-test="errors"]').text()).toContain('needs an image')
  })
})
