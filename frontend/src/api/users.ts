import { http } from '@/api/http'
import { buildQuery } from '@/api/query'
import type { ManagedUser, Paginated, Role } from '@/types/api'

export interface UserListParams {
  search?: string
  role?: Role | ''
  sort?: string
  page?: number
  per_page?: number
}

export interface UserInput {
  name: string
  email: string
  role?: Role
  password?: string
}

export const usersApi = {
  list: (params: UserListParams = {}) =>
    http.get<Paginated<ManagedUser>>(`/api/users${buildQuery(params)}`),

  get: (id: number) => http.get<{ data: ManagedUser }>(`/api/users/${id}`),

  create: (input: UserInput) => http.post<{ data: ManagedUser }>('/api/users', input),

  update: (id: number, input: Partial<UserInput>) =>
    http.patch<{ data: ManagedUser }>(`/api/users/${id}`, input),

  remove: (id: number) => http.delete<void>(`/api/users/${id}`),
}
