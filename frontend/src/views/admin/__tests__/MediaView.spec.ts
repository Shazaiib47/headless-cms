import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import MediaView from '../MediaView.vue'
import { ApiError } from '@/api/http'
import { mediaApi, type MediaListParams } from '@/api/media'
import { useAuthStore } from '@/stores/auth'
import type { MediaItem, Paginated, Role } from '@/types/api'

vi.mock('@/api/media', () => ({
  mediaApi: {
    list: vi.fn<(params?: MediaListParams) => Promise<Paginated<MediaItem>>>(),
    update: vi.fn<(id: number, altText: string | null) => Promise<{ data: MediaItem }>>(),
    remove: vi.fn<(id: number) => Promise<void>>(),
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

function result(items: MediaItem[]): Paginated<MediaItem> {
  return {
    data: items,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 24,
      total: items.length,
      from: items.length ? 1 : null,
      to: items.length || null,
    },
  }
}

async function mountView(url = '/admin/media', role: Role = 'editor') {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore(pinia).user = { id: 1, name: 'Tester', email: 'tester@example.com', role }

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/admin/media', name: 'admin-media', component: { render: () => null } }],
  })
  await router.push(url)
  await router.isReady()

  const wrapper = mount(MediaView, { global: { plugins: [pinia, router] } })
  await flushPromises()
  return { wrapper, router }
}

type Mounted = Awaited<ReturnType<typeof mountView>>['wrapper']

async function openCat(wrapper: Mounted) {
  await wrapper.find('[data-test="media-card"]').trigger('click')
  await flushPromises()
}

describe('MediaView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(mediaApi.list).mockResolvedValue(
      result([item(), item({ id: 2, original_name: 'dog.jpg', alt_text: null })]),
    )
    vi.mocked(mediaApi.remove).mockResolvedValue(undefined)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('shows a loading state, then the images', async () => {
    let resolveList: (value: Paginated<MediaItem>) => void = () => undefined
    vi.mocked(mediaApi.list).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveList = resolve
      }),
    )

    const { wrapper } = await mountView()
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)

    resolveList(result([item(), item({ id: 2, original_name: 'dog.jpg', alt_text: null })]))
    await flushPromises()

    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-test="media-card"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('cat.jpg')
    expect(wrapper.findAll('[data-test="no-alt"]')).toHaveLength(1)
  })

  it('shows an empty state', async () => {
    vi.mocked(mediaApi.list).mockResolvedValue(result([]))

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('No images yet.')
    expect(wrapper.text()).toContain('Upload your first image above.')
  })

  it('shows a no-match state with a working clear button', async () => {
    vi.mocked(mediaApi.list).mockResolvedValue(result([]))
    const { wrapper } = await mountView('/admin/media?search=zzz')

    expect(wrapper.text()).toContain('No images match your search.')

    await wrapper.find('[data-test="clear-filters"]').trigger('click')
    await flushPromises()

    expect(mediaApi.list).toHaveBeenLastCalledWith(expect.objectContaining({ search: '' }))
  })

  it('shows an error and reloads when Try again is clicked', async () => {
    vi.mocked(mediaApi.list)
      .mockRejectedValueOnce(new ApiError(500, 'Server exploded'))
      .mockResolvedValueOnce(result([item({ original_name: 'recovered.jpg' })]))

    const { wrapper } = await mountView()
    expect(wrapper.find('[role="alert"]').text()).toContain('Server exploded')

    await wrapper.find('[data-test="retry"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('recovered.jpg')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('searches after a short pause and returns to page 1', async () => {
    const { wrapper } = await mountView('/admin/media?page=2')
    vi.useFakeTimers()

    await wrapper.find('input[type="search"]').setValue('cat')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(mediaApi.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'cat', page: 1 }),
    )
  })

  it('falls back to safe defaults for tampered query parameters', async () => {
    await mountView('/admin/media?page=-3&search=%20%20')

    expect(mediaApi.list).toHaveBeenCalledWith({ search: '', page: 1, per_page: 24 })
  })

  it("shows an image's details when it is selected", async () => {
    const { wrapper } = await mountView()

    expect(wrapper.find('[data-test="details"]').exists()).toBe(false)

    await openCat(wrapper)

    const details = wrapper.find('[data-test="details"]')
    expect(details.exists()).toBe(true)
    expect(details.text()).toContain('447 × 447 px')
    expect(details.text()).toContain('37.1 KB')
    expect((wrapper.find('#media-alt').element as HTMLTextAreaElement).value).toBe('A cat')
  })

  it('lets an editor save new alt text', async () => {
    vi.mocked(mediaApi.update).mockResolvedValue({ data: item({ alt_text: 'A sleepy cat' }) })
    const { wrapper } = await mountView()
    await openCat(wrapper)

    expect(wrapper.find('[data-test="save-alt"]').attributes('disabled')).toBeDefined()

    await wrapper.find('#media-alt').setValue('A sleepy cat')
    expect(wrapper.find('[data-test="save-alt"]').attributes('disabled')).toBeUndefined()

    await wrapper.find('[data-test="details"] form').trigger('submit')
    await flushPromises()

    expect(mediaApi.update).toHaveBeenCalledWith(1, 'A sleepy cat')
    expect(wrapper.find('[data-test="saved"]').text()).toContain('Alt text saved.')
  })

  it('sends null when the alt text is cleared', async () => {
    vi.mocked(mediaApi.update).mockResolvedValue({ data: item({ alt_text: null }) })
    const { wrapper } = await mountView()
    await openCat(wrapper)

    await wrapper.find('#media-alt').setValue('   ')
    await wrapper.find('[data-test="details"] form').trigger('submit')
    await flushPromises()

    expect(mediaApi.update).toHaveBeenCalledWith(1, null)
  })

  it('gives viewers a read-only view', async () => {
    const { wrapper } = await mountView('/admin/media', 'viewer')

    expect(wrapper.find('[data-test="uploader"]').exists()).toBe(false)

    await openCat(wrapper)

    expect(wrapper.find('#media-alt').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-test="save-alt"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="delete"]').exists()).toBe(false)
  })

  it('shows the uploader and Save to editors, but Delete only to admins', async () => {
    const editor = await mountView('/admin/media', 'editor')
    expect(editor.wrapper.find('[data-test="uploader"]').exists()).toBe(true)
    await openCat(editor.wrapper)
    expect(editor.wrapper.find('[data-test="save-alt"]').exists()).toBe(true)
    expect(editor.wrapper.find('[data-test="delete"]').exists()).toBe(false)

    const admin = await mountView('/admin/media', 'admin')
    await openCat(admin.wrapper)
    expect(admin.wrapper.find('[data-test="delete"]').exists()).toBe(true)
  })

  it('deletes after confirmation and reloads the list', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(mediaApi.list)
      .mockResolvedValueOnce(result([item()]))
      .mockResolvedValueOnce(result([]))
    const { wrapper } = await mountView('/admin/media', 'admin')
    await openCat(wrapper)

    await wrapper.find('[data-test="delete"]').trigger('click')
    await flushPromises()

    expect(mediaApi.remove).toHaveBeenCalledWith(1)
    expect(mediaApi.list).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Deleted "cat.jpg".')
    expect(wrapper.find('[data-test="details"]').exists()).toBe(false)
  })

  it('does not delete when the confirmation is declined', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const { wrapper } = await mountView('/admin/media', 'admin')
    await openCat(wrapper)

    await wrapper.find('[data-test="delete"]').trigger('click')
    await flushPromises()

    expect(mediaApi.remove).not.toHaveBeenCalled()
  })

  it('explains when an image cannot be deleted because pages use it', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(mediaApi.remove).mockRejectedValue(
      new ApiError(422, 'Invalid.', {
        media: [
          'This image is used by 1 page ("Landing page"). Remove it from that page before deleting it.',
        ],
      }),
    )
    const { wrapper } = await mountView('/admin/media', 'admin')
    await openCat(wrapper)

    await wrapper.find('[data-test="delete"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-test="panel-error"]').text()).toContain('Landing page')
    expect(wrapper.find('[data-test="details"]').exists()).toBe(true)
  })

  it('closes the details panel', async () => {
    const { wrapper } = await mountView()
    await openCat(wrapper)

    await wrapper.find('[data-test="close"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-test="details"]').exists()).toBe(false)
  })
})
