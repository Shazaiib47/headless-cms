import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ImagePicker from '../ImagePicker.vue'
import { ApiError } from '@/api/http'
import { mediaApi, type MediaListParams } from '@/api/media'
import { MAX_UPLOAD_BYTES } from '@/composables/useMediaUploads'
import { useAuthStore } from '@/stores/auth'
import type { MediaItem, Paginated, Role } from '@/types/api'

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

function mountPicker(role: Role = 'editor', selectedId: number | null = null) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore(pinia).user = { id: 1, name: 'Tester', email: 'tester@example.com', role }

  return mount(ImagePicker, { props: { selectedId }, global: { plugins: [pinia] } })
}

async function chooseFile(wrapper: ReturnType<typeof mountPicker>, file: File) {
  const input = wrapper.find('[data-test="file"]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
  await flushPromises()
}

describe('ImagePicker', () => {
  beforeAll(() => {
    // jsdom does not implement the modal dialog methods.
    HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) {
      this.setAttribute('open', '')
    }
  })

  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(mediaApi.list).mockResolvedValue(
      page([item(), item({ id: 2, original_name: 'dog.jpg' })]),
    )
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('opens as a dialog, loads the first page and marks the selected image', async () => {
    const wrapper = mountPicker('editor', 2)
    await flushPromises()

    expect(wrapper.find('dialog').attributes('open')).toBeDefined()
    expect(mediaApi.list).toHaveBeenCalledWith({ search: '', page: 1, per_page: 12 })

    const picks = wrapper.findAll('[data-test="pick"]')
    expect(picks).toHaveLength(2)
    expect(picks[0]?.attributes('aria-pressed')).toBe('false')
    expect(picks[1]?.attributes('aria-pressed')).toBe('true')
    expect(picks[0]?.attributes('aria-label')).toBe('Select cat.jpg')
  })

  it('shows an error and reloads when Try again is clicked', async () => {
    vi.mocked(mediaApi.list)
      .mockRejectedValueOnce(new ApiError(500, 'Server exploded'))
      .mockResolvedValueOnce(page([item({ original_name: 'recovered.jpg' })]))

    const wrapper = mountPicker()
    await flushPromises()
    expect(wrapper.find('[role="alert"]').text()).toContain('Server exploded')

    await wrapper.find('[data-test="retry"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('recovered.jpg')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('emits the chosen image', async () => {
    const wrapper = mountPicker()
    await flushPromises()

    await wrapper.findAll('[data-test="pick"]')[1]?.trigger('click')

    expect(wrapper.emitted('select')).toEqual([[item({ id: 2, original_name: 'dog.jpg' })]])
  })

  it('searches after a short pause and returns to page 1', async () => {
    const wrapper = mountPicker()
    await flushPromises()
    vi.useFakeTimers()

    await wrapper.find('input[type="search"]').setValue('cat')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(mediaApi.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'cat', page: 1 }),
    )
  })

  it('closes from the Close button', async () => {
    const wrapper = mountPicker()
    await flushPromises()

    await wrapper.find('[data-test="close"]').trigger('click')

    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('closes when the browser dismisses the dialog (Escape)', async () => {
    const wrapper = mountPicker()
    await flushPromises()

    await wrapper.find('dialog').trigger('close')

    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('closes when the backdrop is clicked, but not when the content is', async () => {
    const wrapper = mountPicker()
    await flushPromises()

    await wrapper.find('h2').trigger('click')
    expect(wrapper.emitted('close')).toBeUndefined()

    await wrapper.find('dialog').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('uploads a new image and selects it', async () => {
    const cat = new File(['x'], 'new.jpg', { type: 'image/jpeg' })
    vi.mocked(mediaApi.upload).mockResolvedValue({
      data: item({ id: 9, original_name: 'new.jpg' }),
    })
    const wrapper = mountPicker()
    await flushPromises()

    await chooseFile(wrapper, cat)

    expect(mediaApi.upload).toHaveBeenCalledWith(cat)
    expect(wrapper.emitted('select')).toEqual([[item({ id: 9, original_name: 'new.jpg' })]])
  })

  it('reports upload problems, and sends nothing for oversized files', async () => {
    vi.mocked(mediaApi.upload).mockRejectedValue(
      new ApiError(422, 'Invalid.', { file: ['The file must be a JPEG, PNG or WebP image.'] }),
    )
    const wrapper = mountPicker()
    await flushPromises()

    await chooseFile(wrapper, new File(['x'], 'notes.jpg', { type: 'image/jpeg' }))
    expect(wrapper.find('[data-test="upload-error"]').text()).toContain(
      'must be a JPEG, PNG or WebP',
    )
    expect(wrapper.emitted('select')).toBeUndefined()

    const big = new File(['x'], 'big.jpg', { type: 'image/jpeg' })
    Object.defineProperty(big, 'size', { value: MAX_UPLOAD_BYTES + 1 })
    await chooseFile(wrapper, big)

    expect(wrapper.find('[data-test="upload-error"]').text()).toContain('5 MB')
    expect(mediaApi.upload).toHaveBeenCalledTimes(1)
  })

  it('offers no upload to viewers', async () => {
    const wrapper = mountPicker('viewer')
    await flushPromises()

    expect(wrapper.find('[data-test="upload"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-test="pick"]')).toHaveLength(2)
  })
})
