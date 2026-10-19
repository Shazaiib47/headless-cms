import { describe, expect, it } from 'vitest'
import { isExternal, safeHref, safeImageSrc } from '@/utils/url'

describe('safeHref', () => {
  it('accepts http(s) URLs, mailto links, relative paths and anchors', () => {
    const safe = [
      'https://example.com/a?b=1#c',
      'http://example.com',
      'mailto:hello@example.com',
      '/',
      '/about/team?x=1',
      '#contact',
      'https://shop.example.com:8443/x',
    ]

    for (const url of safe) {
      expect(safeHref(url)).toBe(url)
    }
  })

  it('rejects dangerous or malformed URLs', () => {
    const unsafe = [
      'javascript:alert(1)',
      'data:text/html;base64,PHNjcmlwdD4=',
      'vbscript:x',
      '//evil.example/path',
      '/\\evil.example',
      'ftp://example.com/file',
      'java\tscript:alert(1)',
      'https://exa mple.com',
      'https://example.com/"onmouseover="x',
      ' https://example.com',
    ]

    for (const url of unsafe) {
      expect(safeHref(url)).toBeNull()
    }
  })

  it('rejects empty, missing and over-long values', () => {
    expect(safeHref('')).toBeNull()
    expect(safeHref(null)).toBeNull()
    expect(safeHref(undefined)).toBeNull()
    expect(safeHref(`/${'a'.repeat(2048)}`)).toBeNull()
  })
})

describe('isExternal', () => {
  it('recognises absolute http(s) URLs only', () => {
    expect(isExternal('https://example.com')).toBe(true)
    expect(isExternal('HTTP://EXAMPLE.COM')).toBe(true)
    expect(isExternal('/about')).toBe(false)
    expect(isExternal('mailto:a@b.co')).toBe(false)
    expect(isExternal('#top')).toBe(false)
  })

  describe('safeImageSrc', () => {
    it('accepts http(s) and site-relative image addresses', () => {
      const safe = [
        'https://cdn.example.com/a.jpg',
        'http://localhost:8000/storage/media/a.jpg',
        '/storage/media/a.jpg',
      ]

      for (const url of safe) {
        expect(safeImageSrc(url)).toBe(url)
      }
    })

    it('rejects anything that is not a plain image address', () => {
      const unsafe = [
        'javascript:alert(1)',
        'data:image/svg+xml;base64,PHN2Zz4=',
        'mailto:a@b.co',
        '#top',
        '//evil.example/a.jpg',
        '',
        null,
        undefined,
      ]

      for (const url of unsafe) {
        expect(safeImageSrc(url)).toBeNull()
      }
    })
  })
})
