import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import UserFormView from '../UserFormView.vue'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { usersApi, type UserInput } from '@/api/users'
import { useAuthStore } from '@/stores/auth'
import type { ManagedUser, User } from '@/types/api'

vi.mock('@/api/users', () => ({
  usersApi: {
    get: vi.fn<(id: number) => Promise<{ data: ManagedUser }>>(),
    create: vi.fn<(input: UserInput) => Promise<{ data: ManagedUser }>>(),
    update: vi.fn<(id: number, input: Partial<UserInput>) => Promise<{ data: ManagedUser }>>(),
  },
}))

vi.mock('@/api/auth', () => ({
  authApi: {
    csrf: vi.fn<() => Promise<void>>(),
    login: vi.fn<(email: string, password: string) => Promise<{ data: User }>>(),
    logout: vi.fn<() => Promise<void>>(),
    user: vi.fn<() => Promise<{ data: User }>>(),
  },
}))

function person(overrides: Partial<ManagedUser> = {}): ManagedUser {
  return {
    id: 5,
    name: 'Ed Editor',
    email: 'ed@example.com',
    role: 'editor',
    created_at: '2026-10-17T10:00:00+00:00',
    ...overrides,
  }
}

const blank = { render: () => null }

async function mountForm(url: string) {
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

  const wrapper = mount(UserFormView, { global: { plugins: [pinia, router] } })
  await flushPromises()
  return { wrapper, router }
}

async function submit(wrapper: Awaited<ReturnType<typeof mountForm>>['wrapper']) {
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

describe('UserFormView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows an empty create form with a required password', async () => {
    const { wrapper } = await mountForm('/admin/users/new')

    expect(wrapper.find('h1').text()).toBe('New user')
    expect(wrapper.find('#password').attributes('required')).toBeDefined()
    expect((wrapper.find('#role').element as HTMLSelectElement).value).toBe('viewer')
    expect(usersApi.get).not.toHaveBeenCalled()
  })

  it('creates a user and returns to the list', async () => {
    vi.mocked(usersApi.create).mockResolvedValue({ data: person({ id: 9 }) })
    const { wrapper, router } = await mountForm('/admin/users/new')

    await wrapper.find('#name').setValue('New Person')
    await wrapper.find('#email').setValue('new@example.com')
    await wrapper.find('#password').setValue('Correct-Horse-9')
    await wrapper.find('#role').setValue('editor')
    await submit(wrapper)

    expect(usersApi.create).toHaveBeenCalledWith({
      name: 'New Person',
      email: 'new@example.com',
      password: 'Correct-Horse-9',
      role: 'editor',
    })
    expect(router.currentRoute.value.name).toBe('admin-users')
  })

  it('shows validation errors next to the fields they belong to', async () => {
    vi.mocked(usersApi.create).mockRejectedValue(
      new ApiError(422, 'Invalid.', { email: ['The email has already been taken.'] }),
    )
    const { wrapper } = await mountForm('/admin/users/new')

    await wrapper.find('#name').setValue('New Person')
    await wrapper.find('#email').setValue('taken@example.com')
    await wrapper.find('#password').setValue('Correct-Horse-9')
    await submit(wrapper)

    expect(wrapper.find('#email-error').text()).toContain('already been taken')
    expect(wrapper.find('#email').attributes('aria-invalid')).toBe('true')
  })

  it('shows a general error when the request fails for another reason', async () => {
    vi.mocked(usersApi.create).mockRejectedValue(new ApiError(500, 'Server exploded'))
    const { wrapper } = await mountForm('/admin/users/new')

    await wrapper.find('#name').setValue('New Person')
    await wrapper.find('#email').setValue('new@example.com')
    await wrapper.find('#password').setValue('Correct-Horse-9')
    await submit(wrapper)

    expect(wrapper.find('[role="alert"]').text()).toContain('Server exploded')
  })

  it('loads an existing user and keeps the password out of the update when blank', async () => {
    vi.mocked(usersApi.get).mockResolvedValue({ data: person() })
    vi.mocked(usersApi.update).mockResolvedValue({ data: person() })
    const { wrapper } = await mountForm('/admin/users/5/edit')

    expect(wrapper.find('h1').text()).toBe('Edit user')
    expect((wrapper.find('#name').element as HTMLInputElement).value).toBe('Ed Editor')
    expect(wrapper.find('#password').attributes('required')).toBeUndefined()

    await submit(wrapper)

    expect(usersApi.update).toHaveBeenCalledWith(5, {
      name: 'Ed Editor',
      email: 'ed@example.com',
      role: 'editor',
    })
    expect(wrapper.text()).toContain('Changes saved.')
  })

  it('sends a new password when one is typed, then clears the field', async () => {
    vi.mocked(usersApi.get).mockResolvedValue({ data: person() })
    vi.mocked(usersApi.update).mockResolvedValue({ data: person() })
    const { wrapper } = await mountForm('/admin/users/5/edit')

    await wrapper.find('#password').setValue('Another-Passw0rd')
    await submit(wrapper)

    expect(usersApi.update).toHaveBeenCalledWith(5, {
      name: 'Ed Editor',
      email: 'ed@example.com',
      role: 'editor',
      password: 'Another-Passw0rd',
    })
    expect((wrapper.find('#password').element as HTMLInputElement).value).toBe('')
  })

  it('locks the role when editing your own account and refreshes the header', async () => {
    vi.mocked(usersApi.get).mockResolvedValue({
      data: person({ id: 1, name: 'Demo Admin', email: 'admin@example.com', role: 'admin' }),
    })
    vi.mocked(usersApi.update).mockResolvedValue({ data: person({ id: 1 }) })
    vi.mocked(authApi.user).mockResolvedValue({
      data: { id: 1, name: 'Demo Admin', email: 'admin@example.com', role: 'admin' },
    })
    const { wrapper } = await mountForm('/admin/users/1/edit')

    expect(wrapper.find('#role').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('You cannot change your own role.')

    await submit(wrapper)

    expect(usersApi.update).toHaveBeenCalledWith(1, {
      name: 'Demo Admin',
      email: 'admin@example.com',
    })
    expect(authApi.user).toHaveBeenCalledTimes(1)
  })

  it('shows a clear message when the user does not exist', async () => {
    vi.mocked(usersApi.get).mockRejectedValue(new ApiError(404, 'No query results for model.'))

    const { wrapper } = await mountForm('/admin/users/999/edit')

    expect(wrapper.find('[role="alert"]').text()).toContain('This user does not exist.')
    expect(wrapper.find('form').exists()).toBe(false)
  })
})
