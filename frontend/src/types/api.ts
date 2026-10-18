export type Role = 'admin' | 'editor' | 'viewer'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}
