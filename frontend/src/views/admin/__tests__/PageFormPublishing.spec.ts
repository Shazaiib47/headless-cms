import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import PageFormView from '../PageFormView.vue'
import { ApiError } from '@/api/http'
import { pagesApi } from '@/api/pages'
import { useAuthStore } from '@/stores/auth'
import type { PageDetail, PageInput, Role } from '@/types/api'

vi.mock('@/api/pages', () => ({
  pagesApi: {
    get: vi.fn<(id: number) => Promise<{ data: PageDetail }>>(),
    create: vi.fn<(input: PageInput) => Promise<{ data: PageDetail }>>(),
    update: vi.fn<(id: number, input: PageInput) => Promise<{ data: PageDetail }>>(),
    publish: vi.fn<(id: number, updatedAt?: string) => Promise<{ data: PageDetail }>>(),
    unpublish: vi.fn<(id: number, updatedAt?: string) => Promise<{ data: PageDetail }>>(),
  },
}))

function detail(overrides: Partial<PageDetail> = {}): PageDetail {
  return {
    id: 7,
    title: 'About us',
    slug: 'about',
    status: 'draft',
    published_at: null,
    created_at: '2026-10-17T10:00:00+00:00',
    updated_at: '2026-10-17T10:00:00+00:00',
    content: [{ id: 'b1', type: 'rich_text', data: { markdown: 'Hello', html: '<p>Hello</p>' } }],
    meta_title: null,
    meta_description: null,
    og_image_id: null,
    created_by: 1,
    updated_by: 1,
    ...overrides,
  }
}

const blank = { render: () => null }
const mounted: { unmount: () => void }[] = []

async function mountForm(url: string, role: Role = 'editor') {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore(pinia).user = { id: 1, name: 'Tester', email: 'tester@example.com', role }

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/pages', name: 'admin-pages', component: blank },
      { path: '/admin/pages/new', name: 'admin-page-new', component: blank },
      { path: '/admin/pages/:id(\\d+)/edit', name: 'admin-page-edit', component: blank },
      { path: '/admin/pages/:id(\\d+)/preview', name: 'admin-page-preview', component: blank },
    ],
  })
  await router.push(url)
  await router.isReady()

  const wrapper = mount(PageFormView, { global: { plugins: [pinia, router] } })
  mounted.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}

type Mounted = Awaited<ReturnType<typeof mountForm>>['wrapper']

function titleValue(wrapper: Mounted): string {
  return (wrapper.find('#page-title').element as HTMLInputElement).value
}

describe('PageFormView publishing', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    vi.restoreAllMocks()
  })

  it('offers Publish and a preview link for a saved draft', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    expect(wrapper.find('[data-test="publish"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="unpublish"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="live-notice"]').exists()).toBe(false)

    const link = wrapper.find('a[data-test="preview"]')
    expect(link.attributes('href')).toBe('/admin/pages/7/preview')
    expect(link.attributes('target')).toBe('_blank')
    expect(link.attributes('rel')).toContain('noopener')
  })

  it('asks to save first when there are unsaved changes', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    expect(wrapper.find('[data-test="publish"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.find('[data-test="save-first"]').exists()).toBe(false)

    await wrapper.find('#page-title').setValue('Changed')

    expect(wrapper.find('[data-test="publish"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-test="save-first"]').exists()).toBe(true)
  })

  it('publishes after confirmation, sending the loaded updated_at', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    vi.mocked(pagesApi.publish).mockResolvedValue({
      data: detail({
        status: 'published',
        published_at: '2026-10-17T11:00:00+00:00',
        updated_at: '2026-10-17T11:00:00+00:00',
      }),
    })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    await wrapper.find('[data-test="publish"]').trigger('click')
    await flushPromises()

    expect(pagesApi.publish).toHaveBeenCalledWith(7, '2026-10-17T10:00:00+00:00')
    expect(wrapper.find('[data-test="publish-notice"]').text()).toContain('Page published.')
    expect(wrapper.find('[data-test="live-notice"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="unpublish"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="publish"]').exists()).toBe(false)
  })

  it('does not publish when the confirmation is declined', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    await wrapper.find('[data-test="publish"]').trigger('click')
    await flushPromises()

    expect(pagesApi.publish).not.toHaveBeenCalled()
    expect(wrapper.find('[data-test="live-notice"]').exists()).toBe(false)
  })

  it('shows publishing problems beside the blocks they belong to', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(pagesApi.get).mockResolvedValue({
      data: detail({
        content: [
          { id: 'i1', type: 'image', data: { image_id: null, alt: 'A cat', caption: null } },
        ],
      }),
    })
    vi.mocked(pagesApi.publish).mockRejectedValue(
      new ApiError(422, 'Invalid.', {
        'content.0.data.image_id': [
          'This image block needs an image before the page can be published.',
        ],
      }),
    )
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    await wrapper.find('[data-test="publish"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('This image block needs an image')
    expect(wrapper.text()).toContain('Please fix the 1 highlighted problem')
    expect(wrapper.find('[data-test="live-notice"]').exists()).toBe(false)
  })

  it('reports a conflict when the page changed since it was opened', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    vi.mocked(pagesApi.publish).mockRejectedValue(
      new ApiError(409, 'This page was changed by someone else.'),
    )
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    await wrapper.find('[data-test="publish"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-test="conflict"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="live-notice"]').exists()).toBe(false)
  })

  it('warns that a published page is live and offers Unpublish', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail({ status: 'published' }) })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    expect(wrapper.find('[data-test="live-notice"]').text()).toContain('This page is live.')
    expect(wrapper.find('[data-test="unpublish"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="publish"]').exists()).toBe(false)
  })

  it('keeps unsaved edits when unpublishing', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail({ status: 'published' }) })
    vi.mocked(pagesApi.unpublish).mockResolvedValue({
      data: detail({ status: 'draft', updated_at: '2026-10-17T11:00:00+00:00' }),
    })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    await wrapper.find('#page-title').setValue('Unsaved title')
    await wrapper.find('[data-test="unpublish"]').trigger('click')
    await flushPromises()

    expect(pagesApi.unpublish).toHaveBeenCalledWith(7, '2026-10-17T10:00:00+00:00')
    expect(titleValue(wrapper)).toBe('Unsaved title')
    expect(wrapper.find('[data-test="publish-notice"]').text()).toContain('Page unpublished.')
    expect(wrapper.find('[data-test="publish"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="save-first"]').exists()).toBe(true)
  })

  it('lets viewers preview but not publish', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    const { wrapper } = await mountForm('/admin/pages/7/edit', 'viewer')

    expect(wrapper.find('[data-test="publishing-panel"]').exists()).toBe(true)
    expect(wrapper.find('a[data-test="preview"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="publish"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="unpublish"]').exists()).toBe(false)
  })

  it('shows no publishing panel for a page that has not been saved yet', async () => {
    const { wrapper } = await mountForm('/admin/pages/new')

    expect(wrapper.find('[data-test="publishing-panel"]').exists()).toBe(false)
  })
})
