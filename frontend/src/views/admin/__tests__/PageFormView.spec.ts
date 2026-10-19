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

async function submit(wrapper: Mounted) {
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

async function addBlock(wrapper: Mounted, label: string) {
  await wrapper
    .findAll('button')
    .find((button) => button.text() === `+ ${label}`)
    ?.trigger('click')
}

function titleValue(wrapper: Mounted): string {
  return (wrapper.find('#page-title').element as HTMLInputElement).value
}

function leavePage(): boolean {
  const event = new Event('beforeunload', { cancelable: true })
  window.dispatchEvent(event)
  return event.defaultPrevented
}

describe('PageFormView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    vi.restoreAllMocks()
  })

  it('fills the slug from the title until the slug is edited by hand', async () => {
    const { wrapper } = await mountForm('/admin/pages/new')
    const slug = () => (wrapper.find('#page-slug').element as HTMLInputElement).value

    await wrapper.find('#page-title').setValue('Summer Sale 2026')
    expect(slug()).toBe('summer-sale-2026')

    await wrapper.find('#page-slug').setValue('custom')
    await wrapper.find('#page-title').setValue('Another title')
    expect(slug()).toBe('custom')
  })

  it('creates a page, then opens it for editing with a confirmation', async () => {
    const saved = detail({ id: 9, title: 'Summer Sale', slug: 'summer-sale', content: [] })
    vi.mocked(pagesApi.create).mockResolvedValue({ data: saved })
    vi.mocked(pagesApi.get).mockResolvedValue({ data: saved })
    const { wrapper, router } = await mountForm('/admin/pages/new')

    await wrapper.find('#page-title').setValue('Summer Sale')
    await submit(wrapper)

    expect(pagesApi.create).toHaveBeenCalledWith({
      title: 'Summer Sale',
      slug: 'summer-sale',
      meta_title: '',
      meta_description: '',
      content: [],
    })
    expect(router.currentRoute.value.name).toBe('admin-page-edit')
    expect(pagesApi.get).toHaveBeenCalledWith(9)
    expect(wrapper.text()).toContain('Page created.')
  })

  it('shows server validation errors beside the fields they belong to', async () => {
    vi.mocked(pagesApi.create).mockRejectedValue(
      new ApiError(422, 'Invalid.', {
        title: ['The title field is required.'],
        'content.0.data.markdown': ['The markdown field is required.'],
      }),
    )
    const { wrapper } = await mountForm('/admin/pages/new')

    await addBlock(wrapper, 'Rich text')
    await submit(wrapper)

    expect(wrapper.find('#page-title-error').text()).toContain('The title field is required.')
    expect(wrapper.find('#page-title').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('The markdown field is required.')
    expect(wrapper.text()).toContain('Please fix the 2 highlighted problems')
  })

  it('loads an existing page into the form', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    expect(wrapper.find('h1').text()).toBe('Edit page')
    expect(titleValue(wrapper)).toBe('About us')
    expect((wrapper.find('textarea[id$="-markdown"]').element as HTMLTextAreaElement).value).toBe(
      'Hello',
    )
    expect(wrapper.text()).toContain('Draft')
  })

  it('sends the loaded updated_at, then the newly returned one', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    vi.mocked(pagesApi.update).mockResolvedValue({
      data: detail({ updated_at: '2026-10-17T11:00:00+00:00' }),
    })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    await wrapper.find('#page-title').setValue('About us v2')
    await submit(wrapper)

    expect(pagesApi.update).toHaveBeenLastCalledWith(
      7,
      expect.objectContaining({ title: 'About us v2', updated_at: '2026-10-17T10:00:00+00:00' }),
    )
    expect(wrapper.text()).toContain('Changes saved.')

    await wrapper.find('#page-title').setValue('About us v3')
    await submit(wrapper)

    expect(pagesApi.update).toHaveBeenLastCalledWith(
      7,
      expect.objectContaining({ updated_at: '2026-10-17T11:00:00+00:00' }),
    )
  })

  it('reports an edit conflict and keeps the edits until the user reloads', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    vi.mocked(pagesApi.update).mockRejectedValue(
      new ApiError(409, 'This page was changed by someone else.'),
    )
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    await wrapper.find('#page-title').setValue('Mine')
    await submit(wrapper)

    expect(wrapper.find('[data-test="conflict"]').exists()).toBe(true)
    expect(titleValue(wrapper)).toBe('Mine')

    await wrapper.find('[data-test="reload"]').trigger('click')
    await flushPromises()

    expect(pagesApi.get).toHaveBeenCalledTimes(2)
    expect(titleValue(wrapper)).toBe('About us')
    expect(wrapper.find('[data-test="conflict"]').exists()).toBe(false)
  })

  it('warns when the slug of a published page is changed', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail({ status: 'published' }) })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    expect(wrapper.find('[data-test="slug-warning"]').exists()).toBe(false)

    await wrapper.find('#page-slug').setValue('new-slug')

    expect(wrapper.find('[data-test="slug-warning"]').text()).toContain('will break')
  })

  it('shows viewers a read-only page', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    const { wrapper } = await mountForm('/admin/pages/7/edit', 'viewer')

    expect(wrapper.find('h1').text()).toBe('View page')
    expect(wrapper.text()).toContain('read-only access')
    expect(wrapper.find('fieldset').attributes('disabled')).toBeDefined()
    expect(wrapper.find('button[type="submit"]').exists()).toBe(false)
    expect(wrapper.findAll('button').some((button) => button.text().startsWith('+ '))).toBe(false)
  })

  it('shows a clear message when the page does not exist', async () => {
    vi.mocked(pagesApi.get).mockRejectedValue(new ApiError(404, 'Not found.'))
    const { wrapper } = await mountForm('/admin/pages/999/edit')

    expect(wrapper.find('[role="alert"]').text()).toContain('This page does not exist.')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('warns before the tab is closed only when there are unsaved changes', async () => {
    vi.mocked(pagesApi.get).mockResolvedValue({ data: detail() })
    const { wrapper } = await mountForm('/admin/pages/7/edit')

    expect(leavePage()).toBe(false)

    await wrapper.find('#page-title').setValue('Changed')

    expect(leavePage()).toBe(true)
  })
})
