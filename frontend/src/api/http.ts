const API_URL: string = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000').replace(
  /\/+$/,
  '',
)

export type ValidationErrors = Record<string, string[]>

export class ApiError extends Error {
  readonly status: number
  readonly errors: ValidationErrors

  constructor(status: number, message: string, errors: ValidationErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  /** The first field-level validation message, or the general message. */
  firstError(): string {
    for (const messages of Object.values(this.errors)) {
      const first = messages[0]
      if (first) return first
    }
    return this.message
  }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

interface RequestOptions {
  /** Set false for calls where a 401 is an expected answer (e.g. "who am I?"). */
  handleUnauthorized?: boolean
}

let unauthorizedHandler: (() => void) | null = null

export function setUnauthorizedHandler(handler: (() => void) | null): void {
  unauthorizedHandler = handler
}

function readCookie(name: string): string | null {
  const prefix = `${name}=`
  for (const part of document.cookie.split('; ')) {
    if (part.startsWith(prefix)) return decodeURIComponent(part.slice(prefix.length))
  }
  return null
}

function csrfCookie(): Promise<void> {
  return request<void>('GET', '/sanctum/csrf-cookie')
}

async function request<T>(
  method: Method,
  path: string,
  body?: unknown,
  options: RequestOptions = {},
  isRetry = false,
): Promise<T> {
  // FormData (file uploads) must be sent as is: the browser adds the multipart boundary.
  const isForm = typeof FormData !== 'undefined' && body instanceof FormData

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json'

  if (method !== 'GET') {
    let token = readCookie('XSRF-TOKEN')
    if (!token) {
      await csrfCookie()
      token = readCookie('XSRF-TOKEN')
    }
    if (token) headers['X-XSRF-TOKEN'] = token
  }

  let response: Response
  try {
    response = await fetch(`${API_URL}${path}`, {
      method,
      headers,
      credentials: 'include',
      body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, 'Could not reach the server. Check your connection and try again.')
  }

  if (response.status === 204) return undefined as T

  const payload: { message?: string; errors?: ValidationErrors } | null = await response
    .json()
    .catch(() => null)

  if (response.ok) return payload as T

  // The CSRF token can go stale (e.g. after login): refresh it and retry once.
  if (response.status === 419 && !isRetry) {
    await csrfCookie()
    return request<T>(method, path, body, options, true)
  }

  if (response.status === 401 && options.handleUnauthorized !== false) {
    unauthorizedHandler?.()
  }

  throw new ApiError(
    response.status,
    payload?.message ?? (response.statusText || 'Request failed'),
    payload?.errors ?? {},
  )
}

export const http = {
  get: <T>(path: string, options?: RequestOptions) => request<T>('GET', path, undefined, options),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>('POST', path, body, options),
  put: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>('PUT', path, body, options),
  patch: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>('PATCH', path, body, options),
  delete: <T>(path: string, options?: RequestOptions) =>
    request<T>('DELETE', path, undefined, options),
}
