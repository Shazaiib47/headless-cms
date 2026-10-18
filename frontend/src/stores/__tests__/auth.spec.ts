import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '@/api/http'
import { authApi } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/types/api'

vi.mock('@/api/auth', () => ({
  authApi: {
    csrf: vi.fn<() => Promise<void>>(),
    login: vi.fn<(email: string, password: string) => Promise<{ data: User }>>(),
    logout: vi.fn<() => Promise<void>>(),
    user: vi.fn<() => Promise<{ data: User }>>(),
  },
}))

const editor: User = { id: 1, name: 'Ed', email: 'ed@example.com', role: 'editor' }

describe('auth store', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    setActivePinia(createPinia())
  })

  it('stores the user after a successful login', async () => {
    vi.mocked(authApi.csrf).mockResolvedValue(undefined)
    vi.mocked(authApi.login).mockResolvedValue({ data: editor })
    const auth = useAuthStore()

    await auth.login('ed@example.com', 'password')

    expect(authApi.csrf).toHaveBeenCalled()
    expect(auth.user).toEqual(editor)
    expect(auth.isAuthenticated).toBe(true)
  })

  it('leaves the user empty and rethrows when login fails', async () => {
    vi.mocked(authApi.csrf).mockResolvedValue(undefined)
    vi.mocked(authApi.login).mockRejectedValue(new ApiError(422, 'Bad credentials.'))
    const auth = useAuthStore()

    await expect(auth.login('ed@example.com', 'wrong')).rejects.toBeInstanceOf(ApiError)
    expect(auth.user).toBeNull()
  })

  it('treats a 401 from /api/user as signed out', async () => {
    vi.mocked(authApi.user).mockRejectedValue(new ApiError(401, 'Unauthenticated.'))
    const auth = useAuthStore()

    await auth.fetchUser()

    expect(auth.user).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
  })

  it('checks the session only once in ensureLoaded', async () => {
    vi.mocked(authApi.user).mockResolvedValue({ data: editor })
    const auth = useAuthStore()

    await auth.ensureLoaded()
    await auth.ensureLoaded()

    expect(authApi.user).toHaveBeenCalledTimes(1)
    expect(auth.user).toEqual(editor)
  })

  it('clears the user on logout even if the request fails', async () => {
    vi.mocked(authApi.logout).mockRejectedValue(new ApiError(0, 'offline'))
    const auth = useAuthStore()
    auth.user = editor

    await auth.logout().catch(() => undefined)

    expect(auth.user).toBeNull()
  })

  it('derives UI permission hints from the role', () => {
    const auth = useAuthStore()

    auth.user = { ...editor, role: 'viewer' }
    expect([auth.canEdit, auth.isAdmin]).toEqual([false, false])

    auth.user = { ...editor, role: 'editor' }
    expect([auth.canEdit, auth.isAdmin]).toEqual([true, false])

    auth.user = { ...editor, role: 'admin' }
    expect([auth.canEdit, auth.isAdmin]).toEqual([true, true])
  })
})
