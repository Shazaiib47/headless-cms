import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import UsersView from '../UsersView.vue'
import { ApiError } from '@/api/http'
import { usersApi, type UserListParams } from '@/api/users'
import { useAuthStore } from '@/stores/auth'
import type { ManagedUser, Paginated } from '@/types/api'

vi.mock('@/api/users', () => ({
  usersApi: {
    list: vi.fn<(params?: UserListParams) => Promise<Paginated<ManagedUser>>>(),
    remove: vi.fn<(id: number) => Promise<void>>(),
  },
}))

function person(overrides: Partial<ManagedUser> = {}): ManagedUser {
  return {
    id: 2,
    name: 'Ed Editor',
    email: 'ed@example.com',
    role: 'editor',
    created_at: '2026-10-17T10:00:00+00:00',
    ...overrides,
  }
}

function result(items: ManagedUser[]): Paginated<ManagedUser> {
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

const blank = { render: () => null }

async function mountView(url = '/admin/users') {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore(pinia).user = {
    id: 1,
    name: 'Demo Admin',
    email: 'admin@example.com',
    role: 'admin',
  }

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/users', name: 'admin-users', component: blank },
      { path: '/admin/users/new', name: 'admin-user-new', component: blank },
      { path: '/admin/users/:id(\\d+)/edit', name: 'admin-user-edit', component: blank },
    ],
  })
  await router.push(url)
  await router.isReady()

  const wrapper = mount(UsersView, { global: { plugins: [pinia, router] } })
  await flushPromises()
  return { wrapper, router }
}

describe('UsersView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(usersApi.list).mockResolvedValue(
      result([
        person({ id: 1, name: 'Demo Admin', email: 'admin@example.com', role: 'admin' }),
        person(),
      ]),
    )
    vi.mocked(usersApi.remove).mockResolvedValue(undefined)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('shows a loading state, then the users', async () => {
    let resolveList: (value: Paginated<ManagedUser>) => void = () => undefined
    vi.mocked(usersApi.list).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveList = resolve
      }),
    )

    const { wrapper } = await mountView()
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)

    resolveList(result([person()]))
    await flushPromises()

    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Ed Editor')
    expect(wrapper.text()).toContain('ed@example.com')
    expect(wrapper.text()).toContain('editor')
  })

  it('shows a no-match state with a working clear button', async () => {
    vi.mocked(usersApi.list).mockResolvedValue(result([]))
    const { wrapper } = await mountView('/admin/users?search=zzz')

    expect(wrapper.text()).toContain('No users match your filters')

    await wrapper.find('[data-test="clear-filters"]').trigger('click')
    await flushPromises()

    expect(usersApi.list).toHaveBeenLastCalledWith(expect.objectContaining({ search: '' }))
  })

  it('shows an error and reloads when Try again is clicked', async () => {
    vi.mocked(usersApi.list)
      .mockRejectedValueOnce(new ApiError(500, 'Server exploded'))
      .mockResolvedValueOnce(result([person({ name: 'Recovered' })]))

    const { wrapper } = await mountView()
    expect(wrapper.find('[role="alert"]').text()).toContain('Server exploded')

    await wrapper.find('[data-test="retry"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Recovered')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('marks your own row and offers no delete button for it', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('You')
    expect(wrapper.find('button[aria-label="Delete Demo Admin"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label="Delete Ed Editor"]').exists()).toBe(true)
    expect(wrapper.find('a[aria-label="Edit Demo Admin"]').exists()).toBe(true)
  })

  it('deletes after confirmation and reloads the list', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const { wrapper } = await mountView()

    await wrapper.find('button[aria-label="Delete Ed Editor"]').trigger('click')
    await flushPromises()

    expect(usersApi.remove).toHaveBeenCalledWith(2)
    expect(usersApi.list).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Deleted Ed Editor')
  })

  it('does not delete when the confirmation is declined', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const { wrapper } = await mountView()

    await wrapper.find('button[aria-label="Delete Ed Editor"]').trigger('click')
    await flushPromises()

    expect(usersApi.remove).not.toHaveBeenCalled()
  })

  it('shows the server message when a delete is rejected', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(usersApi.remove).mockRejectedValue(
      new ApiError(422, 'Invalid.', { user: ['You cannot delete your own account.'] }),
    )
    const { wrapper } = await mountView()

    await wrapper.find('button[aria-label="Delete Ed Editor"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('You cannot delete your own account.')
  })

  it('searches after a short pause and returns to page 1', async () => {
    const { wrapper } = await mountView('/admin/users?page=2')
    vi.useFakeTimers()

    await wrapper.find('input[type="search"]').setValue('quark')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(usersApi.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'quark', page: 1 }),
    )
  })

  it('falls back to safe defaults for tampered query parameters', async () => {
    await mountView('/admin/users?role=superuser&sort=password&page=-3&search=%20%20')

    expect(usersApi.list).toHaveBeenCalledWith({
      search: '',
      role: '',
      sort: 'name',
      page: 1,
      per_page: 10,
    })
  })
})
