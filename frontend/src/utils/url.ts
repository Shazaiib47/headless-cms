/** Characters that never belong in a link: control characters, whitespace, quotes, brackets. */
function hasUnsafeCharacter(url: string): boolean {
  for (const char of url) {
    const code = char.codePointAt(0) ?? 0

    if (code <= 0x20 || code === 0x7f || /\s/.test(char) || '<>"\'`\\'.includes(char)) {
      return true
    }
  }
  return false
}

/**
 * Returns the URL when it is safe to use as a link, otherwise null. Mirrors the
 * server's SafeUrl rule: http(s), mailto:, /relative paths and #anchors only.
 */
export function safeHref(url: string | null | undefined): string | null {
  if (typeof url !== 'string' || url === '' || url.length > 2048 || hasUnsafeCharacter(url)) {
    return null
  }

  if (url.startsWith('#')) return url

  if (url.startsWith('/')) return url.startsWith('//') ? null : url // // is off-site

  if (/^https?:\/\//i.test(url)) {
    try {
      new URL(url)
      return url
    } catch {
      return null
    }
  }

  return /^mailto:[^@\s]+@[^@\s]+$/i.test(url) ? url : null
}

/** For `<img src>`: only http(s) and site-relative addresses (no mailto:, anchors or data: URLs). */
export function safeImageSrc(url: string | null | undefined): string | null {
  const href = safeHref(url)

  if (href === null || href.startsWith('#') || href.toLowerCase().startsWith('mailto:')) {
    return null
  }

  return href
}

export function isExternal(url: string): boolean {
  return /^https?:\/\//i.test(url)
}
