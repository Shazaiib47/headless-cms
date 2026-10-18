import { http } from '@/api/http'
import type { User } from '@/types/api'

export const authApi = {
  csrf: () => http.get<void>('/sanctum/csrf-cookie'),
  login: (email: string, password: string) =>
    http.post<{ data: User }>('/api/login', { email, password }),
  logout: () => http.post<void>('/api/logout'),
  user: () => http.get<{ data: User }>('/api/user', { handleUnauthorized: false }),
}
