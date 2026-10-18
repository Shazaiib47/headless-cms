import type { User } from '@/types/api'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import LoginView from '../LoginView.vue'
import { ApiError } from '@/api/http'
import { authApi } from '@/api/auth'

vi.mock('@/api/auth', () => ({
  authApi: {
    csrf: vi.fn<() => Promise<void>>(),
    login: vi.fn<(email: string, password: string) => Promise<{ data: User }>>(),
    logout: vi.fn<() => Promise<void>>(),
    user: vi.fn<() => Promise<{ data: User }>>(),
  },
}))

const blank = { render: () => null }

async function mountLogin(url = '/admin/login') {
  const pinia = createPinia()
  setActivePinia(pinia)

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/login', name: 'login', component: blank },
      { path: '/admin/pages', name: 'admin-pages', component: blank },
      { path: '/admin/other', name: 'other', component: blank },
    ],
  })
  await router.push(url)
  await router.isReady()

  const wrapper = mount(LoginView, { global: { plugins: [pinia, router] } })
  return { wrapper, router }
}

async function submit(wrapper: Awaited<ReturnType<typeof mountLogin>>['wrapper']) {
  await wrapper.find('input[type="email"]').setValue('ed@example.com')
  await wrapper.find('input[type="password"]').setValue('password')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

const editor = { id: 1, name: 'Ed', email: 'ed@example.com', role: 'editor' as const }

describe('LoginView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('renders labelled email and password fields', async () => {
    const { wrapper } = await mountLogin()

    expect(wrapper.find('label[for="email"]').exists()).toBe(true)
    expect(wrapper.find('input#email[type="email"]').exists()).toBe(true)
    expect(wrapper.find('label[for="password"]').exists()).toBe(true)
    expect(wrapper.find('input#password[type="password"]').exists()).toBe(true)
  })

  it('shows the server error when credentials are rejected', async () => {
    vi.mocked(authApi.csrf).mockResolvedValue(undefined)
    vi.mocked(authApi.login).mockRejectedValue(
      new ApiError(422, 'Invalid.', { email: ['These credentials do not match our records.'] }),
    )
    const { wrapper } = await mountLogin()

    await submit(wrapper)

    expect(wrapper.find('[role="alert"]').text()).toContain('do not match our records')
  })

  it('shows a lockout message after too many attempts', async () => {
    vi.mocked(authApi.csrf).mockResolvedValue(undefined)
    vi.mocked(authApi.login).mockRejectedValue(new ApiError(429, 'Too many attempts.'))
    const { wrapper } = await mountLogin()

    await submit(wrapper)

    expect(wrapper.find('[role="alert"]').text()).toContain('Too many login attempts')
  })

  it('goes to the pages list after a successful login', async () => {
    vi.mocked(authApi.csrf).mockResolvedValue(undefined)
    vi.mocked(authApi.login).mockResolvedValue({ data: editor })
    const { wrapper, router } = await mountLogin()

    await submit(wrapper)

    expect(router.currentRoute.value.name).toBe('admin-pages')
  })

  it('follows a safe admin redirect', async () => {
    vi.mocked(authApi.csrf).mockResolvedValue(undefined)
    vi.mocked(authApi.login).mockResolvedValue({ data: editor })
    const { wrapper, router } = await mountLogin('/admin/login?redirect=/admin/other')

    await submit(wrapper)

    expect(router.currentRoute.value.name).toBe('other')
  })

  it('ignores a redirect that leaves the admin area', async () => {
    vi.mocked(authApi.csrf).mockResolvedValue(undefined)
    vi.mocked(authApi.login).mockResolvedValue({ data: editor })
    const { wrapper, router } = await mountLogin('/admin/login?redirect=https://evil.example')

    await submit(wrapper)

    expect(router.currentRoute.value.name).toBe('admin-pages')
  })
})
