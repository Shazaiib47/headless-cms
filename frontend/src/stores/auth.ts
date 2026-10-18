import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '@/api/auth'
import type { User } from '@/types/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialised = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  // Presentation hints only: the API enforces the real permissions.
  const canEdit = computed(() => user.value?.role === 'admin' || user.value?.role === 'editor')
  const isAdmin = computed(() => user.value?.role === 'admin')

  async function fetchUser(): Promise<void> {
    try {
      const response = await authApi.user()
      user.value = response.data
    } catch {
      user.value = null
    }
  }

  /** Check the session once per page load (used by the route guard). */
  async function ensureLoaded(): Promise<void> {
    if (initialised.value) return
    await fetchUser()
    initialised.value = true
  }

  async function login(email: string, password: string): Promise<void> {
    await authApi.csrf()
    const response = await authApi.login(email, password)
    user.value = response.data
    initialised.value = true
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      user.value = null
    }
  }

  function clear(): void {
    user.value = null
  }

  return {
    user,
    initialised,
    isAuthenticated,
    canEdit,
    isAdmin,
    fetchUser,
    ensureLoaded,
    login,
    logout,
    clear,
  }
})
