import { describe, expect, it } from 'vitest'
import { toRenderBlocks } from '@/blocks/render'

describe('toRenderBlocks', () => {
  it('keeps only the generated html of rich text', () => {
    const blocks = toRenderBlocks([
      { id: 'r', type: 'rich_text', data: { html: '<p>Hi</p>', markdown: '**x**' } },
    ])

    expect(blocks).toEqual([{ id: 'r', type: 'rich_text', data: { html: '<p>Hi</p>' } }])
  })

  it('turns nulls from the API into empty strings for the other blocks', () => {
    const blocks = toRenderBlocks([
      {
        id: 'h',
        type: 'hero',
        data: {
          heading: 'Hi',
          subheading: null,
          image_id: null,
          button_label: null,
          button_url: null,
        },
      },
    ])

    expect(blocks).toEqual([
      {
        id: 'h',
        type: 'hero',
        data: { heading: 'Hi', subheading: '', image_id: null, button_label: '', button_url: '' },
      },
    ])
  })

  it('skips unknown and malformed blocks', () => {
    const blocks = toRenderBlocks([
      'nope',
      null,
      { id: 'x', type: 'script', data: {} },
      { id: 'ok', type: 'rich_text', data: { html: '<p>a</p>' } },
    ])

    expect(blocks).toHaveLength(1)
    expect(blocks[0]?.id).toBe('ok')
  })

  it('gives empty html when rich text has none, and nothing for non-arrays', () => {
    expect(toRenderBlocks([{ id: 'a', type: 'rich_text', data: {} }])).toEqual([
      { id: 'a', type: 'rich_text', data: { html: '' } },
    ])
    expect(toRenderBlocks('nope')).toEqual([])
  })
})
