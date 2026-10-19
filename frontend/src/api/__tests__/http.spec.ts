import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, http, setUnauthorizedHandler } from '@/api/http'

type Call = [string, RequestInit & { headers: Record<string, string> }]

const fetchMock = vi.fn<typeof fetch>()

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function lastCall(): Call {
  return fetchMock.mock.lastCall as unknown as Call
}

describe('http client', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', fetchMock)
    document.cookie = 'XSRF-TOKEN=abc%3D123; path=/'
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    setUnauthorizedHandler(null)
    document.cookie = 'XSRF-TOKEN=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT'
  })

  it('sends credentials and a JSON Accept header', async () => {
    fetchMock.mockResolvedValueOnce(json({ ok: true }))

    await http.get('/api/health')

    const [url, init] = lastCall()
    expect(url).toMatch(/\/api\/health$/)
    expect(init.credentials).toBe('include')
    expect(init.headers.Accept).toBe('application/json')
  })

  it('sends the decoded XSRF cookie as a header on mutating requests', async () => {
    fetchMock.mockResolvedValueOnce(json({ data: 1 }, 201))

    await http.post('/api/pages', { title: 'x' })

    const [, init] = lastCall()
    expect(init.method).toBe('POST')
    expect(init.headers['X-XSRF-TOKEN']).toBe('abc=123')
    expect(init.body).toBe(JSON.stringify({ title: 'x' }))
  })

  it('resolves undefined for 204 responses', async () => {
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }))

    await expect(http.post('/api/logout')).resolves.toBeUndefined()
  })

  it('throws an ApiError carrying validation errors', async () => {
    fetchMock.mockResolvedValueOnce(
      json({ message: 'Invalid.', errors: { slug: ['The slug has already been taken.'] } }, 422),
    )

    const error = await http.post('/api/pages', {}).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).firstError()).toBe('The slug has already been taken.')
  })

  it('refreshes the CSRF cookie and retries once on a 419', async () => {
    fetchMock
      .mockResolvedValueOnce(json({ message: 'CSRF token mismatch.' }, 419))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(json({ data: 'ok' }))

    await expect(http.post('/api/pages', {})).resolves.toEqual({ data: 'ok' })
    expect(fetchMock).toHaveBeenCalledTimes(3)
  })

  it('calls the unauthorized handler on 401, unless told not to', async () => {
    const handler = vi.fn<() => void>()
    setUnauthorizedHandler(handler)

    fetchMock.mockResolvedValueOnce(json({ message: 'Unauthenticated.' }, 401))
    await http.get('/api/pages').catch(() => undefined)
    expect(handler).toHaveBeenCalledTimes(1)

    fetchMock.mockResolvedValueOnce(json({ message: 'Unauthenticated.' }, 401))
    await http.get('/api/user', { handleUnauthorized: false }).catch(() => undefined)
    expect(handler).toHaveBeenCalledTimes(1)
  })

  it('reports network failures as an ApiError with status 0', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))

    const error = await http.get('/api/pages').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(0)
  })

  it('sends FormData as it is, without forcing a JSON content type', async () => {
    fetchMock.mockResolvedValueOnce(json({ data: 1 }, 201))
    const form = new FormData()
    form.append('file', new File(['x'], 'a.jpg', { type: 'image/jpeg' }))

    await http.post('/api/media', form)

    const [, init] = lastCall()
    expect(init.body).toBe(form)
    expect(init.headers['Content-Type']).toBeUndefined()
    expect(init.headers['X-XSRF-TOKEN']).toBe('abc=123')
  })
})
