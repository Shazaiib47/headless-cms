import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import PagePreviewView from '../PagePreviewView.vue'
import { ApiError } from '@/api/http'
import { pagesApi } from '@/api/pages'
import type { PreviewPage } from '@/types/render'

vi.mock('@/api/pages', () => ({
  pagesApi: {
    preview: vi.fn<(id: number) => Promise<{ data: PreviewPage }>>(),
  },
}))

function preview(overrides: Partial<PreviewPage> = {}): PreviewPage {
  return {
    title: 'About us',
    slug: 'about',
    status: 'draft',
    content: [
      { id: 'r', type: 'rich_text', data: { html: '<p>Hello <strong>world</strong></p>' } },
    ],
    meta_title: null,
    meta_description: null,
    og_image_id: null,
    published_at: null,
    updated_at: '2026-10-17T10:00:00+00:00',
    ...overrides,
  }
}

const blank = { render: () => null }

async function mountView(url = '/admin/pages/7/preview') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/pages/:id(\\d+)/preview', name: 'admin-page-preview', component: blank },
      { path: '/admin/pages/:id(\\d+)/edit', name: 'admin-page-edit', component: blank },
    ],
  })
  await router.push(url)
  await router.isReady()

  const wrapper = mount(PagePreviewView, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

describe('PagePreviewView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows a loading state, then the rendered page', async () => {
    let resolvePreview: (value: { data: PreviewPage }) => void = () => undefined
    vi.mocked(pagesApi.preview).mockReturnValueOnce(
      new Promise((resolve) => {
        resolvePreview = resolve
      }),
    )

    const { wrapper } = await mountView()
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)

    resolvePreview({ data: preview() })
    await flushPromises()

    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false)
    expect(wrapper.find('h1').text()).toBe('About us')
    expect(wrapper.find('strong').text()).toBe('world')
    expect(pagesApi.preview).toHaveBeenCalledWith(7)
  })

  it('tells the user a draft is not public', async () => {
    vi.mocked(pagesApi.preview).mockResolvedValue({ data: preview({ status: 'draft' }) })

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('This page is a draft and is not public.')
    expect(wrapper.text()).toContain('Draft')
  })

  it('tells the user when the page is live', async () => {
    vi.mocked(pagesApi.preview).mockResolvedValue({ data: preview({ status: 'published' }) })

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('This page is live.')
    expect(wrapper.text()).toContain('Published')
  })

  it('shows a clear message when the page does not exist', async () => {
    vi.mocked(pagesApi.preview).mockRejectedValue(new ApiError(404, 'Not found.'))

    const { wrapper } = await mountView('/admin/pages/999/preview')

    expect(wrapper.find('[role="alert"]').text()).toContain('This page does not exist.')
    expect(wrapper.find('article').exists()).toBe(false)
  })

  it('loads the preview again when asked to reload', async () => {
    vi.mocked(pagesApi.preview).mockResolvedValue({ data: preview() })
    const { wrapper } = await mountView()

    await wrapper.find('[data-test="reload"]').trigger('click')
    await flushPromises()

    expect(pagesApi.preview).toHaveBeenCalledTimes(2)
  })

  it('links back to the editor for the same page', async () => {
    vi.mocked(pagesApi.preview).mockResolvedValue({ data: preview() })

    const { wrapper } = await mountView()

    expect(wrapper.find('a[data-test="back"]').attributes('href')).toBe('/admin/pages/7/edit')
  })
})
