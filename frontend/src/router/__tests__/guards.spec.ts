import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import router from '@/router'
import type { Role, User } from '@/types/api'

vi.mock('@/api/auth', () => ({
  authApi: {
    csrf: vi.fn<() => Promise<void>>(),
    login: vi.fn<(email: string, password: string) => Promise<{ data: User }>>(),
    logout: vi.fn<() => Promise<void>>(),
    user: vi.fn<() => Promise<{ data: User }>>(),
  },
}))

function signInAs(role: Role | null) {
  setActivePinia(createPinia())

  if (role) {
    vi.mocked(authApi.user).mockResolvedValue({
      data: { id: 1, name: 'Tester', email: 'tester@example.com', role },
    })
  } else {
    vi.mocked(authApi.user).mockRejectedValue(new ApiError(401, 'Unauthenticated.'))
  }
}

describe('route guards', () => {
  beforeEach(async () => {
    vi.resetAllMocks()
    await router.push('/')
  })

  it('sends guests to the login screen and remembers where they were going', async () => {
    signInAs(null)

    await router.push('/admin/pages')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/admin/pages')
  })

  it('lets signed-in users reach the pages list', async () => {
    signInAs('viewer')

    await router.push('/admin/pages')

    expect(router.currentRoute.value.name).toBe('admin-pages')
  })

  it('keeps non-admins out of user management', async () => {
    signInAs('editor')

    await router.push('/admin/users')

    expect(router.currentRoute.value.name).toBe('admin-pages')
  })

  it('lets admins into user management', async () => {
    signInAs('admin')

    await router.push('/admin/users')

    expect(router.currentRoute.value.name).toBe('admin-users')
  })

  it('sends signed-in users away from the login screen', async () => {
    signInAs('viewer')

    await router.push('/admin/login')

    expect(router.currentRoute.value.name).toBe('admin-pages')
  })

  it('lets editors open the new-page form', async () => {
    signInAs('editor')

    await router.push('/admin/pages/new')

    expect(router.currentRoute.value.name).toBe('admin-page-new')
  })

  it('keeps viewers out of the new-page form', async () => {
    signInAs('viewer')

    await router.push('/admin/pages/new')

    expect(router.currentRoute.value.name).toBe('admin-pages')
  })

  it('lets viewers open an existing page read-only', async () => {
    signInAs('viewer')

    await router.push('/admin/pages/5/edit')

    expect(router.currentRoute.value.name).toBe('admin-page-edit')
  })

  it('lets viewers open a page preview', async () => {
    signInAs('viewer')

    await router.push('/admin/pages/5/preview')

    expect(router.currentRoute.value.name).toBe('admin-page-preview')
  })
})
