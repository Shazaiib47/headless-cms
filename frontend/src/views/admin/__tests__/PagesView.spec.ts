import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import PagesView from '../PagesView.vue'
import { ApiError } from '@/api/http'
import { pagesApi, type PageListParams } from '@/api/pages'
import { useAuthStore } from '@/stores/auth'
import type { Paginated, PageSummary, Role } from '@/types/api'

vi.mock('@/api/pages', () => ({
  pagesApi: {
    list: vi.fn<(params?: PageListParams) => Promise<Paginated<PageSummary>>>(),
    remove: vi.fn<(id: number) => Promise<void>>(),
  },
}))

function page(overrides: Partial<PageSummary> = {}): PageSummary {
  return {
    id: 1,
    title: 'About us',
    slug: 'about',
    status: 'published',
    published_at: '2026-10-17T10:00:00+00:00',
    created_at: '2026-10-17T10:00:00+00:00',
    updated_at: '2026-10-17T10:00:00+00:00',
    ...overrides,
  }
}

function result(items: PageSummary[]): Paginated<PageSummary> {
  return {
    data: items,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: items.length,
      from: items.length ? 1 : null,
      to: items.length || null,
    },
  }
}

async function mountView(url = '/admin/pages', role: Role | null = 'viewer') {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore(pinia).user = role
    ? { id: 1, name: 'Tester', email: 'tester@example.com', role }
    : null

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/pages', name: 'admin-pages', component: { render: () => null } },
      { path: '/admin/pages/new', name: 'admin-page-new', component: { render: () => null } },
      {
        path: '/admin/pages/:id(\\d+)/edit',
        name: 'admin-page-edit',
        component: { render: () => null },
      },
    ],
  })
  await router.push(url)
  await router.isReady()

  const wrapper = mount(PagesView, { global: { plugins: [pinia, router] } })
  await flushPromises()
  return { wrapper, router }
}

describe('PagesView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(pagesApi.list).mockResolvedValue(result([page()]))
    vi.mocked(pagesApi.remove).mockResolvedValue(undefined)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('shows a loading state, then the pages', async () => {
    let resolveList: (value: Paginated<PageSummary>) => void = () => undefined
    vi.mocked(pagesApi.list).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveList = resolve
      }),
    )

    const { wrapper } = await mountView()
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)

    resolveList(result([page({ title: 'About us', slug: 'about' })]))
    await flushPromises()

    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('About us')
    expect(wrapper.text()).toContain('/about')
    expect(wrapper.text()).toContain('Published')
  })

  it('shows an empty state when there are no pages', async () => {
    vi.mocked(pagesApi.list).mockResolvedValue(result([]))

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('No pages yet')
  })

  it('shows a no-match state with a working clear button when filters are active', async () => {
    vi.mocked(pagesApi.list).mockResolvedValue(result([]))
    const { wrapper } = await mountView('/admin/pages?search=zzz')

    expect(wrapper.text()).toContain('No pages match your filters')

    await wrapper.find('[data-test="clear-filters"]').trigger('click')
    await flushPromises()

    expect(pagesApi.list).toHaveBeenLastCalledWith(expect.objectContaining({ search: '' }))
  })

  it('shows an error and reloads when Try again is clicked', async () => {
    vi.mocked(pagesApi.list)
      .mockRejectedValueOnce(new ApiError(500, 'Server exploded'))
      .mockResolvedValueOnce(result([page({ title: 'Recovered' })]))

    const { wrapper } = await mountView()
    expect(wrapper.find('[role="alert"]').text()).toContain('Server exploded')

    await wrapper.find('[data-test="retry"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Recovered')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('searches after a short pause and returns to page 1', async () => {
    const { wrapper } = await mountView('/admin/pages?page=2')
    vi.useFakeTimers()

    await wrapper.find('input[type="search"]').setValue('summer')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(pagesApi.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'summer', page: 1 }),
    )
  })

  it('falls back to safe defaults for tampered query parameters', async () => {
    await mountView('/admin/pages?status=bogus&sort=content&page=-3&search=%20%20')

    expect(pagesApi.list).toHaveBeenCalledWith({
      search: '',
      status: '',
      sort: '-updated_at',
      page: 1,
      per_page: 10,
    })
  })

  it('shows delete buttons to admins only', async () => {
    const admin = await mountView('/admin/pages', 'admin')
    expect(admin.wrapper.find('button[aria-label="Delete About us"]').exists()).toBe(true)

    const viewer = await mountView('/admin/pages', 'viewer')
    expect(viewer.wrapper.find('button[aria-label="Delete About us"]').exists()).toBe(false)

    const editor = await mountView('/admin/pages', 'editor')
    expect(editor.wrapper.find('button[aria-label="Delete About us"]').exists()).toBe(false)
  })

  it('deletes after confirmation and reloads the list', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const { wrapper } = await mountView('/admin/pages', 'admin')

    await wrapper.find('button[aria-label="Delete About us"]').trigger('click')
    await flushPromises()

    expect(pagesApi.remove).toHaveBeenCalledWith(1)
    expect(pagesApi.list).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Deleted "About us"')
  })

  it('does not delete when the confirmation is declined', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const { wrapper } = await mountView('/admin/pages', 'admin')

    await wrapper.find('button[aria-label="Delete About us"]').trigger('click')
    await flushPromises()

    expect(pagesApi.remove).not.toHaveBeenCalled()
  })

  it('offers New page and Edit to editors, but only View to viewers', async () => {
    const editor = await mountView('/admin/pages', 'editor')
    expect(editor.wrapper.text()).toContain('New page')
    expect(editor.wrapper.find('a[aria-label="Edit About us"]').exists()).toBe(true)

    const viewer = await mountView('/admin/pages', 'viewer')
    expect(viewer.wrapper.text()).not.toContain('New page')
    expect(viewer.wrapper.find('a[aria-label="View About us"]').exists()).toBe(true)
  })
})
