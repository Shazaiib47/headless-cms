import { describe, expect, it } from 'vitest'
import { slugify } from '@/utils/slug'

describe('slugify', () => {
  it('lowercases and joins words with hyphens', () => {
    expect(slugify('Summer Sale 2026')).toBe('summer-sale-2026')
  })

  it('removes accents', () => {
    expect(slugify('Café déjà vu')).toBe('cafe-deja-vu')
  })

  it('collapses symbols and trims the ends', () => {
    expect(slugify('  --Hello,   World!! ')).toBe('hello-world')
  })

  it('caps the length without leaving a trailing hyphen', () => {
    expect(slugify('a'.repeat(300))).toHaveLength(255)
    expect(slugify(`${'a'.repeat(254)} b`)).toBe('a'.repeat(254))
  })

  it('returns an empty string when nothing usable remains', () => {
    expect(slugify('!!! ???')).toBe('')
  })
})
