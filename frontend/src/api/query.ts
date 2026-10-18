/** Builds "?a=1&b=2", skipping empty values. Returns "" when nothing is set. */
export function buildQuery(params: object): string {
  const query = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') query.set(key, String(value))
  }

  const qs = query.toString()
  return qs ? `?${qs}` : ''
}
